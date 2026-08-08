<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

use DateTimeImmutable;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use SensitiveParameter;

final readonly class SigningCredentials
{
    /** @param list<X509Certificate> $certificateChain */
    private function __construct(
        private OpenSSLAsymmetricKey $privateKey,
        public X509Certificate $certificate,
        public array $certificateChain,
    ) {
        $details = openssl_pkey_get_details($privateKey);

        if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidArgumentException('DIAN signing credentials require an RSA private key.');
        }

        if (! openssl_x509_check_private_key($certificate->resource(), $privateKey)) {
            throw new InvalidArgumentException('The private key does not match the signing certificate.');
        }
    }

    /** @param list<string> $chainCertificatePems */
    public static function fromPem(
        #[SensitiveParameter]
        string $privateKeyPem,
        string $certificatePem,
        array $chainCertificatePems = [],
        #[SensitiveParameter]
        ?string $passphrase = null,
    ): self {
        $privateKey = openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');

        if ($privateKey === false) {
            throw new InvalidArgumentException('Private key or passphrase is invalid.');
        }

        return new self(
            privateKey: $privateKey,
            certificate: X509Certificate::fromPem($certificatePem),
            certificateChain: array_map(
                static fn (string $pem): X509Certificate => X509Certificate::fromPem($pem),
                $chainCertificatePems,
            ),
        );
    }

    public static function fromPkcs12(
        #[SensitiveParameter]
        string $contents,
        #[SensitiveParameter]
        string $password,
    ): self {
        $store = [];

        if (! openssl_pkcs12_read($contents, $store, $password)) {
            throw new InvalidArgumentException('PKCS#12 contents or password are invalid.');
        }

        if (! is_array($store)) {
            throw new InvalidArgumentException('PKCS#12 contents have an unsupported format.');
        }

        $privateKey = $store['pkey'] ?? null;
        $certificate = $store['cert'] ?? null;
        $extraCertificates = $store['extracerts'] ?? [];

        if (! is_string($privateKey) || ! is_string($certificate)) {
            throw new InvalidArgumentException('PKCS#12 does not contain a private key and certificate.');
        }

        if (is_string($extraCertificates)) {
            $extraCertificates = [$extraCertificates];
        }

        if (! is_array($extraCertificates)) {
            throw new InvalidArgumentException('PKCS#12 certificate chain has an unsupported format.');
        }

        $chain = [];

        foreach ($extraCertificates as $extraCertificate) {
            if (! is_string($extraCertificate)) {
                throw new InvalidArgumentException('PKCS#12 certificate chain contains a non-string value.');
            }

            $chain[] = $extraCertificate;
        }

        return self::fromPem($privateKey, $certificate, $chain);
    }

    public function assertValidAt(DateTimeImmutable $signingTime): void
    {
        $timestamp = $signingTime->getTimestamp();

        if ($timestamp < $this->certificate->validFrom || $timestamp > $this->certificate->validTo) {
            throw new InvalidArgumentException('Signing time is outside the signing certificate validity period.');
        }
    }

    public function signSha256(string $canonicalSignedInfo): string
    {
        $signature = '';

        if (! openssl_sign($canonicalSignedInfo, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('OpenSSL could not create the RSA-SHA256 signature.');
        }

        if (! is_string($signature)) {
            throw new RuntimeException('OpenSSL returned an unsupported signature value.');
        }

        return $signature;
    }

    /** @return non-empty-list<X509Certificate> */
    public function signingCertificatePath(): array
    {
        return [$this->certificate, ...$this->certificateChain];
    }
}

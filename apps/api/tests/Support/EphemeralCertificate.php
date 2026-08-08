<?php

declare(strict_types=1);

namespace Tests\Support;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * Generates throwaway signing material for tests.
 *
 * Nothing here is ever written to the repository: a real certificate must
 * arrive through a secret mount.
 */
final readonly class EphemeralCertificate
{
    private function __construct(
        public string $privateKeyPem,
        public string $certificatePem,
        private OpenSSLAsymmetricKey $key,
        private OpenSSLCertificate $certificate,
    ) {}

    public static function create(): self
    {
        $options = self::opensslOptions();
        $key = openssl_pkey_new($options);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('OpenSSL could not generate an ephemeral RSA key.');
        }

        $request = openssl_csr_new([
            'countryName' => 'CO',
            'organizationName' => 'Tribux Test',
            'commonName' => 'Ephemeral Tribux Issuer',
        ], $key, $options);

        if (! $request instanceof OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('OpenSSL could not generate an ephemeral certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 2, $options, 314159);

        if (! $certificate instanceof OpenSSLCertificate) {
            throw new RuntimeException('OpenSSL could not self-sign an ephemeral certificate.');
        }

        $privateKeyPem = '';
        $certificatePem = '';

        if (
            ! openssl_pkey_export($key, $privateKeyPem, null, $options)
            || ! is_string($privateKeyPem)
            || ! openssl_x509_export($certificate, $certificatePem)
            || ! is_string($certificatePem)
        ) {
            throw new RuntimeException('OpenSSL could not export ephemeral signing material.');
        }

        return new self($privateKeyPem, $certificatePem, $key, $certificate);
    }

    public function pkcs12(string $password): string
    {
        $bundle = '';

        if (! openssl_pkcs12_export($this->certificate, $bundle, $this->key, $password) || ! is_string($bundle)) {
            throw new RuntimeException('OpenSSL could not export an ephemeral PKCS#12 bundle.');
        }

        return $bundle;
    }

    /** @return array<string, int|string> */
    private static function opensslOptions(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];
        $windowsConfiguration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (is_file($windowsConfiguration)) {
            $options['config'] = $windowsConfiguration;
        }

        return $options;
    }
}

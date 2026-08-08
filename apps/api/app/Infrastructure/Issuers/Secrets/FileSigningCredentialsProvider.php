<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers\Secrets;

use App\Application\Issuers\Contracts\SigningCredentialsProvider;
use App\Application\Issuers\Exceptions\SecretNotAvailable;
use InvalidArgumentException;
use Tribux\Dian\Signing\SigningCredentials;

/**
 * Loads signing material from a secret mount, PKCS#12 first:
 *
 *   {TRIBUX_SECRETS_PATH}/{reference}/certificate.p12
 *   {TRIBUX_SECRETS_PATH}/{reference}/certificate_password
 *
 * or, when no PKCS#12 is mounted, separate PEM files:
 *
 *   {TRIBUX_SECRETS_PATH}/{reference}/certificate.pem
 *   {TRIBUX_SECRETS_PATH}/{reference}/private_key.pem
 *   {TRIBUX_SECRETS_PATH}/{reference}/private_key_passphrase   (optional)
 *   {TRIBUX_SECRETS_PATH}/{reference}/chain.pem                (optional)
 *
 * OpenSSL failures are rewritten so the reason survives but the password and
 * key material never appear in an exception message or a stack trace.
 */
final readonly class FileSigningCredentialsProvider implements SigningCredentialsProvider
{
    public const string PKCS12 = 'certificate.p12';

    public const string PKCS12_PASSWORD = 'certificate_password';

    public const string CERTIFICATE_PEM = 'certificate.pem';

    public const string PRIVATE_KEY_PEM = 'private_key.pem';

    public const string PRIVATE_KEY_PASSPHRASE = 'private_key_passphrase';

    public const string CHAIN_PEM = 'chain.pem';

    public function __construct(private MountedSecretFiles $files) {}

    public function forReference(string $credentialReference): SigningCredentials
    {
        try {
            return $this->files->has($credentialReference, self::PKCS12)
                ? $this->fromPkcs12($credentialReference)
                : $this->fromPem($credentialReference);
        } catch (InvalidArgumentException $exception) {
            throw SecretNotAvailable::unusableSigningMaterial($credentialReference, $exception->getMessage());
        }
    }

    private function fromPkcs12(string $reference): SigningCredentials
    {
        return SigningCredentials::fromPkcs12(
            $this->files->contents($reference, self::PKCS12),
            $this->files->has($reference, self::PKCS12_PASSWORD)
                ? $this->files->line($reference, self::PKCS12_PASSWORD)
                : '',
        );
    }

    private function fromPem(string $reference): SigningCredentials
    {
        return SigningCredentials::fromPem(
            privateKeyPem: $this->files->contents($reference, self::PRIVATE_KEY_PEM),
            certificatePem: $this->files->contents($reference, self::CERTIFICATE_PEM),
            chainCertificatePems: $this->chain($reference),
            passphrase: $this->files->has($reference, self::PRIVATE_KEY_PASSPHRASE)
                ? $this->files->line($reference, self::PRIVATE_KEY_PASSPHRASE)
                : null,
        );
    }

    /** @return list<string> */
    private function chain(string $reference): array
    {
        if (! $this->files->has($reference, self::CHAIN_PEM)) {
            return [];
        }

        $matches = [];
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $this->files->contents($reference, self::CHAIN_PEM),
            $matches,
        );

        return array_values($matches[0]);
    }
}

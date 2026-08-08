<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;
use Tribux\Dian\Signing\SigningCredentials;

final class EphemeralSigningCredentials
{
    public static function create(): SigningCredentials
    {
        $options = self::opensslOptions();
        $key = openssl_pkey_new($options);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('OpenSSL could not generate an ephemeral RSA key.');
        }

        $requestKey = $key;
        $request = openssl_csr_new([
            'countryName' => 'CO',
            'organizationName' => 'Tribux Test',
            'commonName' => 'Ephemeral Tribux Client',
        ], $requestKey, $options);

        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('OpenSSL could not generate an ephemeral certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 2, $options, 173205);

        if (! $certificate instanceof \OpenSSLCertificate) {
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

        return SigningCredentials::fromPem($privateKeyPem, $certificatePem);
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

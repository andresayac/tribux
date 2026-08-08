<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

use InvalidArgumentException;
use OpenSSLCertificate;

final readonly class X509Certificate
{
    private function __construct(
        public string $pem,
        public string $der,
        public string $base64,
        public string $issuerName,
        public string $serialNumber,
        public int $validFrom,
        public int $validTo,
        private OpenSSLCertificate $resource,
    ) {
    }

    public static function fromPem(string $pem): self
    {
        $resource = openssl_x509_read($pem);

        if ($resource === false) {
            throw new InvalidArgumentException('Certificate must contain a valid PEM-encoded X.509 certificate.');
        }

        $parsed = openssl_x509_parse($resource, true);

        if ($parsed === false) {
            throw new InvalidArgumentException('Could not parse the X.509 certificate.');
        }

        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        $der = is_string($base64) ? base64_decode($base64, true) : false;
        $issuer = $parsed['issuer'] ?? null;
        $serial = $parsed['serialNumber'] ?? null;
        $validFrom = $parsed['validFrom_time_t'] ?? null;
        $validTo = $parsed['validTo_time_t'] ?? null;

        if (
            ! is_string($base64)
            || $base64 === ''
            || ! is_string($der)
            || ! is_array($issuer)
            || (! is_string($serial) && ! is_int($serial))
            || ! is_int($validFrom)
            || ! is_int($validTo)
        ) {
            throw new InvalidArgumentException('Certificate is missing required X.509 metadata.');
        }

        return new self(
            pem: $pem,
            der: $der,
            base64: $base64,
            issuerName: self::issuerName($issuer),
            serialNumber: (string) $serial,
            validFrom: $validFrom,
            validTo: $validTo,
            resource: $resource,
        );
    }

    public function resource(): OpenSSLCertificate
    {
        return $this->resource;
    }

    /** @param array<mixed, mixed> $issuer */
    private static function issuerName(array $issuer): string
    {
        $parts = [];

        foreach ($issuer as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! is_scalar($item)) {
                    continue;
                }

                $text = (string) $item;

                if ($name === 'emailAddress') {
                    $parts[] = '1.2.840.113549.1.9.1=#16'.self::derLength(strlen($text)).bin2hex($text);
                    continue;
                }

                $parts[] = $name.'='.self::escapeDistinguishedNameValue($text);
            }
        }

        if ($parts === []) {
            throw new InvalidArgumentException('Certificate issuer name cannot be empty.');
        }

        return implode(',', $parts);
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return str_pad(dechex($length), 2, '0', STR_PAD_LEFT);
        }

        $hex = dechex($length);
        $hex = strlen($hex) % 2 === 0 ? $hex : '0'.$hex;

        return dechex(0x80 | intdiv(strlen($hex), 2)).$hex;
    }

    private static function escapeDistinguishedNameValue(string $value): string
    {
        $escaped = strtr($value, [
            '\\' => '\\\\',
            ',' => '\\,',
            '+' => '\\+',
            '"' => '\\"',
            '<' => '\\<',
            '>' => '\\>',
            ';' => '\\;',
        ]);

        if (str_starts_with($escaped, ' ') || str_starts_with($escaped, '#')) {
            $escaped = '\\'.$escaped;
        }

        if (str_ends_with($escaped, ' ')) {
            $escaped = substr($escaped, 0, -1).'\\ ';
        }

        return $escaped;
    }
}

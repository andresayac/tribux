<?php

declare(strict_types=1);

namespace App\Application\Issuers\Exceptions;

use RuntimeException;

/**
 * A secret could not be resolved.
 *
 * Messages name the credential reference and what was expected, never a value,
 * a password or a decoded certificate.
 */
final class SecretNotAvailable extends RuntimeException
{
    public static function missingFile(string $reference, string $expected): self
    {
        return new self(sprintf(
            'No "%s" secret is mounted for credential reference "%s".',
            $expected,
            $reference,
        ));
    }

    public static function unconfigured(string $reference): self
    {
        return new self(sprintf(
            'No secrets path is configured, so credential reference "%s" cannot be resolved. Set TRIBUX_SECRETS_PATH.',
            $reference,
        ));
    }

    public static function unusableReference(string $reference): self
    {
        return new self(sprintf(
            'Credential reference "%s" is not a safe path segment.',
            $reference,
        ));
    }

    public static function unreadable(string $reference, string $expected): self
    {
        return new self(sprintf(
            'The "%s" secret for credential reference "%s" could not be read or is empty.',
            $expected,
            $reference,
        ));
    }

    public static function unusableSigningMaterial(string $reference, string $reason): self
    {
        return new self(sprintf(
            'The signing material for credential reference "%s" is unusable: %s',
            $reference,
            $reason,
        ));
    }
}

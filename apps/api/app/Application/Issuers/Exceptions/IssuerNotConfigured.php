<?php

declare(strict_types=1);

namespace App\Application\Issuers\Exceptions;

use RuntimeException;

/**
 * Configuration is missing, not invalid. The message names the issuer and where
 * Tribux looked, because the usual cause is an unmounted configuration file.
 */
final class IssuerNotConfigured extends RuntimeException
{
    public static function withReference(string $issuerId, string $source): self
    {
        return new self(sprintf(
            'No issuer profile is configured for "%s". Checked %s.',
            $issuerId,
            $source,
        ));
    }
}

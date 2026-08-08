<?php

declare(strict_types=1);

namespace App\Application\Issuers\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The configuration source exists but cannot be understood.
 *
 * The message names the file and the offending field only. It never echoes the
 * file contents, which may contain taxpayer data.
 */
final class IssuerConfigurationInvalid extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self(sprintf('The issuer configuration file "%s" does not exist or cannot be read.', $path));
    }

    public static function notJson(string $path): self
    {
        return new self(sprintf('The issuer configuration file "%s" does not contain a JSON object.', $path));
    }

    public static function forIssuer(string $path, string $issuerId, Throwable $previous): self
    {
        return new self(
            sprintf('Issuer "%s" in "%s" is invalid: %s', $issuerId, $path, $previous->getMessage()),
            0,
            $previous,
        );
    }
}

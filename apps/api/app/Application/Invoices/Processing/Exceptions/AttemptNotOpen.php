<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Exceptions;

use RuntimeException;

/**
 * A closed or unknown attempt cannot be advanced or closed again. Reopening one
 * would let a second worker continue a submission somebody else finished.
 */
final class AttemptNotOpen extends RuntimeException
{
    public static function withId(string $attemptId): self
    {
        return new self(sprintf('Processing attempt "%s" is missing or already closed.', $attemptId));
    }
}

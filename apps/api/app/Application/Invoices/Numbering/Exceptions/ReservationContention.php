<?php

declare(strict_types=1);

namespace App\Application\Invoices\Numbering\Exceptions;

use RuntimeException;

/**
 * Too many writers raced for the same counter.
 *
 * This is a retryable local failure, not a numbering error: nothing was
 * consumed, so the caller may try again.
 */
final class ReservationContention extends RuntimeException
{
    public static function after(string $what, int $attempts): self
    {
        return new self(sprintf(
            'Could not reserve %s after %d attempts because of concurrent writers.',
            $what,
            $attempts,
        ));
    }
}

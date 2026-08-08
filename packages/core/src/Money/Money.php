<?php

declare(strict_types=1);

namespace Tribux\Core\Money;

use InvalidArgumentException;

/**
 * Minimal money value object for the foundation phase.
 *
 * Amount is stored as a canonical decimal string to avoid binary floating point.
 * Arithmetic policy/scale/rounding will be introduced via a dedicated ADR.
 */
final readonly class Money
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if (!preg_match('/^-?\\d+(?:\\.\\d{1,6})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a decimal string with up to 6 fraction digits.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO-like 3-letter uppercase code.');
        }
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Core\Tax;

use InvalidArgumentException;

final readonly class TaxRate
{
    public function __construct(public string $percent)
    {
        if (!preg_match('/^\\d+(?:\\.\\d{1,4})?$/', $percent)) {
            throw new InvalidArgumentException('Tax rate must be a non-negative decimal percentage.');
        }
    }
}

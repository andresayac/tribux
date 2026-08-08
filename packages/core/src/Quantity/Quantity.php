<?php

declare(strict_types=1);

namespace Tribux\Core\Quantity;

use InvalidArgumentException;

final readonly class Quantity
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^\d+(?:\.\d{1,6})?$/', $value)) {
            throw new InvalidArgumentException('Quantity must be a positive decimal string with up to 6 fraction digits.');
        }

        if (trim($value, '0.') === '') {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }
}

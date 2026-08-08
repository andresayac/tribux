<?php

declare(strict_types=1);

namespace Tribux\Core\Tax;

use InvalidArgumentException;

final readonly class TaxRate
{
    public string $percent;

    public function __construct(string $percent)
    {
        if (!preg_match('/^\\d+(?:\\.\\d{1,4})?$/', $percent)) {
            throw new InvalidArgumentException('Tax rate must be a non-negative decimal percentage.');
        }

        [$integer, $fraction] = array_pad(explode('.', $percent, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $this->percent = $fraction === '' ? $integer : $integer.'.'.$fraction;
    }
}

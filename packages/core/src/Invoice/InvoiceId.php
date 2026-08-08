<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

use InvalidArgumentException;

final readonly class InvoiceId
{
    public function __construct(public string $value)
    {
        if ($value === '' || strlen($value) > 100) {
            throw new InvalidArgumentException('InvoiceId must be non-empty and <= 100 chars.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

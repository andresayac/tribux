<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

use InvalidArgumentException;
use Tribux\Core\Money\Money;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\Tax;

final readonly class InvoiceLine
{
    /** @param list<Tax> $taxes */
    public function __construct(
        public string $description,
        public Quantity $quantity,
        public Money $unitPrice,
        public array $taxes = [],
    ) {
        if (trim($description) === '' || strlen($description) > 1000) {
            throw new InvalidArgumentException('Invoice line description must be non-empty and at most 1000 characters.');
        }

        if (str_starts_with($unitPrice->amount, '-')) {
            throw new InvalidArgumentException('Invoice line unit price cannot be negative.');
        }

        foreach ($taxes as $tax) {
            if (!$tax instanceof Tax) {
                throw new InvalidArgumentException('Invoice line taxes must contain only Tax values.');
            }
        }
    }
}

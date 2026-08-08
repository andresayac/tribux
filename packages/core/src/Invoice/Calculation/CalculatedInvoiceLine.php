<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice\Calculation;

use Tribux\Core\Money\Money;

final readonly class CalculatedInvoiceLine
{
    /** @param list<CalculatedTax> $taxes */
    public function __construct(
        public Money $lineExtensionAmount,
        public array $taxes,
        public Money $taxAmount,
        public Money $taxInclusiveAmount,
    ) {
    }
}

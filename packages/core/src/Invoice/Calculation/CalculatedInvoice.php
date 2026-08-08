<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice\Calculation;

use Tribux\Core\Money\Money;

final readonly class CalculatedInvoice
{
    /**
     * @param non-empty-list<CalculatedInvoiceLine> $lines
     * @param list<CalculatedTax> $taxes
     */
    public function __construct(
        public array $lines,
        public array $taxes,
        public Money $lineExtensionAmount,
        public Money $taxExclusiveAmount,
        public Money $taxAmount,
        public Money $taxInclusiveAmount,
        public Money $payableAmount,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceTaxTotal
{
    /** @param non-empty-list<InvoiceTaxSubtotal> $subtotals */
    public function __construct(
        public string $taxAmount,
        public array $subtotals,
    ) {
        Fev19Value::amount($taxAmount, 'taxTotal.taxAmount');

        if ($subtotals === []) {
            throw new InvalidArgumentException('taxTotal.subtotals must contain at least one subtotal.');
        }

        foreach ($subtotals as $subtotal) {
            if (! $subtotal instanceof InvoiceTaxSubtotal) {
                throw new InvalidArgumentException('taxTotal.subtotals must contain InvoiceTaxSubtotal values only.');
            }
        }
    }
}

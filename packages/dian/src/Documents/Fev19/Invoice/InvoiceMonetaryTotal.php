<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceMonetaryTotal
{
    public function __construct(
        public string $lineExtensionAmount,
        public string $taxExclusiveAmount,
        public string $taxInclusiveAmount,
        public string $payableAmount,
    ) {
        Fev19Value::amount($lineExtensionAmount, 'totals.lineExtensionAmount');
        Fev19Value::amount($taxExclusiveAmount, 'totals.taxExclusiveAmount');
        Fev19Value::amount($taxInclusiveAmount, 'totals.taxInclusiveAmount');
        Fev19Value::amount($payableAmount, 'totals.payableAmount');
    }
}

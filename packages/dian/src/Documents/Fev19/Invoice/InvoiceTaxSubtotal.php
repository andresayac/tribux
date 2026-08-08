<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceTaxSubtotal
{
    public function __construct(
        public string $taxableAmount,
        public string $taxAmount,
        public string $percent,
        public string $taxSchemeId,
        public string $taxSchemeName,
    ) {
        Fev19Value::amount($taxableAmount, 'taxSubtotal.taxableAmount');
        Fev19Value::amount($taxAmount, 'taxSubtotal.taxAmount');
        Fev19Value::decimal($percent, 'taxSubtotal.percent');
        Fev19Value::code($taxSchemeId, 'taxSubtotal.taxSchemeId');
        Fev19Value::text($taxSchemeName, 'taxSubtotal.taxSchemeName');
    }
}

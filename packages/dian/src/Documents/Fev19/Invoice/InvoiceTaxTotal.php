<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceTaxTotal
{
    public function __construct(
        public string $taxableAmount,
        public string $taxAmount,
        public string $percent,
        public string $taxSchemeId,
        public string $taxSchemeName,
    ) {
        Fev19Value::amount($taxableAmount, 'tax.taxableAmount');
        Fev19Value::amount($taxAmount, 'tax.taxAmount');
        Fev19Value::decimal($percent, 'tax.percent');
        Fev19Value::code($taxSchemeId, 'tax.taxSchemeId');
        Fev19Value::text($taxSchemeName, 'tax.taxSchemeName');
    }
}

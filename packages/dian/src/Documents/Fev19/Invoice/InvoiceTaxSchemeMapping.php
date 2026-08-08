<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceTaxSchemeMapping
{
    public function __construct(
        public string $coreTaxType,
        public string $dianId,
        public string $dianName,
    ) {
        Fev19Value::text($coreTaxType, 'taxMapping.coreTaxType');
        Fev19Value::code($dianId, 'taxMapping.dianId');
        Fev19Value::text($dianName, 'taxMapping.dianName');
    }
}

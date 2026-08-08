<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceLine
{
    /** @param list<InvoiceTaxTotal> $taxes */
    public function __construct(
        public string $id,
        public string $quantity,
        public string $unitCode,
        public string $lineExtensionAmount,
        public string $description,
        public string $priceAmount,
        public string $baseQuantity,
        public bool $freeOfCharge = false,
        public array $taxes = [],
    ) {
        Fev19Value::text($id, 'line.id');
        Fev19Value::decimal($quantity, 'line.quantity');
        Fev19Value::code($unitCode, 'line.unitCode');
        Fev19Value::amount($lineExtensionAmount, 'line.lineExtensionAmount');
        Fev19Value::text($description, 'line.description');
        Fev19Value::amount($priceAmount, 'line.priceAmount');
        Fev19Value::decimal($baseQuantity, 'line.baseQuantity');

        foreach ($taxes as $tax) {
            if (! $tax instanceof InvoiceTaxTotal) {
                throw new InvalidArgumentException('line.taxes must contain InvoiceTaxTotal values only.');
            }
        }
    }
}

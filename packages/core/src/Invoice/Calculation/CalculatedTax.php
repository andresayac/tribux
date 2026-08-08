<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice\Calculation;

use Tribux\Core\Money\Money;
use Tribux\Core\Tax\TaxRate;

final readonly class CalculatedTax
{
    public function __construct(
        public string $type,
        public TaxRate $rate,
        public Money $taxableAmount,
        public Money $taxAmount,
    ) {
    }

    public function plus(self $other): self
    {
        if ($this->type !== $other->type || $this->rate->percent !== $other->rate->percent) {
            throw new \InvalidArgumentException('Only equal tax type/rate buckets can be combined.');
        }

        return new self(
            type: $this->type,
            rate: $this->rate,
            taxableAmount: $this->taxableAmount->plus($other->taxableAmount),
            taxAmount: $this->taxAmount->plus($other->taxAmount),
        );
    }
}

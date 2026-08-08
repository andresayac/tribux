<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice\Calculation;

use InvalidArgumentException;
use Tribux\Core\Decimal\DecimalRoundingMode;

final readonly class InvoiceCalculationPolicy
{
    public function __construct(
        public int $moneyScale,
        public DecimalRoundingMode $roundingMode,
    ) {
        if ($moneyScale < 0 || $moneyScale > 6) {
            throw new InvalidArgumentException('Invoice money scale must be between 0 and 6.');
        }
    }
}

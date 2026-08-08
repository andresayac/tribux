<?php

declare(strict_types=1);

namespace Tribux\Core\Money;

use InvalidArgumentException;
use Tribux\Core\Decimal\DecimalArithmetic;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\TaxRate;

/**
 * Amount is stored as a canonical decimal string to avoid binary floating point.
 * Operations that can discard precision require an explicit scale and rounding
 * mode; Tribux does not hide a fiscal rounding policy in this generic type.
 */
final readonly class Money
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if (!preg_match('/^-?\\d+(?:\\.\\d{1,6})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a decimal string with up to 6 fraction digits.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO-like 3-letter uppercase code.');
        }
    }

    public function plus(self $addend): self
    {
        $this->requireSameCurrency($addend);

        return new self(DecimalArithmetic::add($this->amount, $addend->amount), $this->currency);
    }

    public function multipliedBy(
        Quantity $quantity,
        int $scale,
        DecimalRoundingMode $roundingMode,
    ): self {
        self::requireSupportedScale($scale);

        return new self(
            DecimalArithmetic::multiply($this->amount, $quantity->value, $scale, $roundingMode),
            $this->currency,
        );
    }

    public function percentage(
        TaxRate $rate,
        int $scale,
        DecimalRoundingMode $roundingMode,
    ): self {
        self::requireSupportedScale($scale);

        return new self(
            DecimalArithmetic::percentage($this->amount, $rate->percent, $scale, $roundingMode),
            $this->currency,
        );
    }

    private function requireSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money operations require the same currency.');
        }
    }

    private static function requireSupportedScale(int $scale): void
    {
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Money operation scale must be between 0 and 6.');
        }
    }
}

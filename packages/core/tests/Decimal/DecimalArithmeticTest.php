<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Decimal;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Decimal\DecimalArithmetic;
use Tribux\Core\Decimal\DecimalRoundingMode;

final class DecimalArithmeticTest extends TestCase
{
    #[Test]
    public function it_adds_arbitrary_length_values_exactly(): void
    {
        self::assertSame(
            '1000000000000000000.00',
            DecimalArithmetic::add('999999999999999999.99', '0.01'),
        );
        self::assertSame('-0.50', DecimalArithmetic::add('1.25', '-1.75'));
    }

    #[Test]
    public function it_multiplies_without_binary_floating_point(): void
    {
        self::assertSame(
            '59999.97',
            DecimalArithmetic::multiply('19999.99', '3.00', 2, DecimalRoundingMode::HalfUp),
        );
    }

    #[Test]
    public function it_requires_rounding_policy_when_precision_is_discarded(): void
    {
        self::assertSame('0.00', DecimalArithmetic::quantize('0.005', 2, DecimalRoundingMode::Down));
        self::assertSame('0.01', DecimalArithmetic::quantize('0.005', 2, DecimalRoundingMode::HalfUp));
        self::assertSame('-1.00', DecimalArithmetic::quantize('-1.005', 2, DecimalRoundingMode::Down));
        self::assertSame('-1.01', DecimalArithmetic::quantize('-1.005', 2, DecimalRoundingMode::HalfUp));
    }

    #[Test]
    public function it_calculates_percentage_values_exactly(): void
    {
        self::assertSame(
            '19000.00',
            DecimalArithmetic::percentage('100000.00', '19.00', 2, DecimalRoundingMode::HalfUp),
        );
    }
}

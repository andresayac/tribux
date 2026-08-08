<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Money;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Core\Money\Money;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\TaxRate;

final class MoneyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidAmounts(): iterable
    {
        yield 'float-like exponent' => ['1e3'];
        yield 'too many decimals' => ['1.1234567'];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function it_rejects_non_canonical_amounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money($amount, 'COP');
    }

    #[Test]
    public function it_performs_exact_currency_safe_operations(): void
    {
        $unitPrice = new Money('19999.99', 'COP');
        $lineTotal = $unitPrice->multipliedBy(new Quantity('3.00'), 2, DecimalRoundingMode::HalfUp);
        $tax = $lineTotal->percentage(new TaxRate('19.00'), 2, DecimalRoundingMode::HalfUp);

        self::assertSame('59999.97', $lineTotal->amount);
        self::assertSame('11399.99', $tax->amount);
        self::assertSame('71399.96', $lineTotal->plus($tax)->amount);
    }

    #[Test]
    public function it_rejects_arithmetic_across_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('same currency');

        (new Money('1.00', 'COP'))->plus(new Money('1.00', 'USD'));
    }

    #[Test]
    public function it_normalizes_equivalent_tax_rate_representations(): void
    {
        self::assertSame('19', (new TaxRate('019.0000'))->percent);
        self::assertSame('0', (new TaxRate('0.0000'))->percent);
    }
}

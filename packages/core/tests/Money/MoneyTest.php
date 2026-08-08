<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Money;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Money\Money;

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
}

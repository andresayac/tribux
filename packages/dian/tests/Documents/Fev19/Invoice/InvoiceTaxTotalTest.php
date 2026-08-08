<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Documents\Fev19\Invoice;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxSubtotal;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxTotal;

final class InvoiceTaxTotalTest extends TestCase
{
    #[Test]
    public function it_supports_multiple_rate_subtotals(): void
    {
        $tax = new InvoiceTaxTotal('24000.00', [
            new InvoiceTaxSubtotal('100000.00', '19000.00', '19', '01', 'IVA'),
            new InvoiceTaxSubtotal('100000.00', '5000.00', '5', '01', 'IVA'),
        ]);

        self::assertCount(2, $tax->subtotals);
        self::assertSame('24000.00', $tax->taxAmount);
    }

    #[Test]
    public function it_rejects_a_total_without_subtotals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one subtotal');

        new InvoiceTaxTotal('0.00', []);
    }
}

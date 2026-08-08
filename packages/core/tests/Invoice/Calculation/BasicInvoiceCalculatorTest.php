<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Invoice\Calculation;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Core\Invoice\Calculation\BasicInvoiceCalculator;
use Tribux\Core\Invoice\Calculation\InvoiceCalculationPolicy;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceId;
use Tribux\Core\Invoice\InvoiceLine;
use Tribux\Core\Money\Money;
use Tribux\Core\Party\Party;
use Tribux\Core\Party\TaxIdentifier;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\Tax;
use Tribux\Core\Tax\TaxRate;

final class BasicInvoiceCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_and_aggregates_tax_exclusive_lines(): void
    {
        $invoice = new Invoice(
            id: new InvoiceId('invoice-calculation-fixture'),
            issuerId: 'issuer-fixture',
            customer: new Party(new TaxIdentifier('800123456'), 'Cliente Fixture'),
            lines: [
                new InvoiceLine(
                    'Servicio A',
                    new Quantity('3.00'),
                    new Money('19999.99', 'COP'),
                    [new Tax('VAT', new TaxRate('19'))],
                ),
                new InvoiceLine(
                    'Servicio B',
                    new Quantity('2.00'),
                    new Money('10000.00', 'COP'),
                    [new Tax('VAT', new TaxRate('19.00'))],
                ),
            ],
            createdAt: new DateTimeImmutable('2026-08-08T10:30:00-05:00'),
        );

        $result = (new BasicInvoiceCalculator())->calculate(
            $invoice,
            new InvoiceCalculationPolicy(2, DecimalRoundingMode::HalfUp),
        );

        self::assertSame('59999.97', $result->lines[0]->lineExtensionAmount->amount);
        self::assertSame('11399.99', $result->lines[0]->taxAmount->amount);
        self::assertSame('79999.97', $result->lineExtensionAmount->amount);
        self::assertSame('15199.99', $result->taxAmount->amount);
        self::assertSame('95199.96', $result->payableAmount->amount);
        self::assertCount(1, $result->taxes);
        self::assertSame('79999.97', $result->taxes[0]->taxableAmount->amount);
        self::assertSame('15199.99', $result->taxes[0]->taxAmount->amount);
    }

    #[Test]
    public function it_keeps_distinct_tax_rates_in_distinct_buckets(): void
    {
        $invoice = new Invoice(
            id: new InvoiceId('invoice-tax-buckets'),
            issuerId: 'issuer-fixture',
            customer: new Party(new TaxIdentifier('800123456'), 'Cliente Fixture'),
            lines: [
                new InvoiceLine(
                    'Producto gravado',
                    new Quantity('1'),
                    new Money('100.00', 'COP'),
                    [new Tax('VAT', new TaxRate('19'))],
                ),
                new InvoiceLine(
                    'Producto tarifa reducida',
                    new Quantity('1'),
                    new Money('100.00', 'COP'),
                    [new Tax('VAT', new TaxRate('5'))],
                ),
            ],
            createdAt: new DateTimeImmutable('2026-08-08T10:30:00-05:00'),
        );

        $result = (new BasicInvoiceCalculator())->calculate(
            $invoice,
            new InvoiceCalculationPolicy(2, DecimalRoundingMode::HalfUp),
        );

        self::assertCount(2, $result->taxes);
        self::assertSame('24.00', $result->taxAmount->amount);
    }
}

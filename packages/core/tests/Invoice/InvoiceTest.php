<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Invoice;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceId;
use Tribux\Core\Invoice\InvoiceLine;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Core\Money\Money;
use Tribux\Core\Party\Party;
use Tribux\Core\Party\TaxIdentifier;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\Tax;
use Tribux\Core\Tax\TaxRate;

final class InvoiceTest extends TestCase
{
    #[Test]
    public function it_builds_a_framework_independent_invoice(): void
    {
        $invoice = new Invoice(
            id: new InvoiceId('0198d7f3-a02c-7b57-a63f-f14136007e64'),
            issuerId: 'issuer_demo',
            customer: new Party(new TaxIdentifier('900123456', 'NIT'), 'Empresa Ejemplo SAS'),
            lines: [
                new InvoiceLine(
                    'Servicio de desarrollo',
                    new Quantity('1.00'),
                    new Money('100000.00', 'COP'),
                    [new Tax('VAT', new TaxRate('19.00'))],
                ),
            ],
            createdAt: new DateTimeImmutable('2026-08-08T12:00:00-05:00'),
        );

        self::assertSame(InvoiceStatus::Queued, $invoice->status);
        self::assertSame('COP', $invoice->lines[0]->unitPrice->currency);
    }

    #[Test]
    public function it_rejects_mixed_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('same currency');

        new Invoice(
            id: new InvoiceId('invoice-id'),
            issuerId: 'issuer_demo',
            customer: new Party(new TaxIdentifier('900123456'), 'Customer'),
            lines: [
                new InvoiceLine('Line one', new Quantity('1'), new Money('10.00', 'COP')),
                new InvoiceLine('Line two', new Quantity('1'), new Money('3.00', 'USD')),
            ],
            createdAt: new DateTimeImmutable('2026-08-08T12:00:00Z'),
        );
    }
}

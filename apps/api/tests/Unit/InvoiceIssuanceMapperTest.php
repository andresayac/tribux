<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Invoices\InvoiceMapper;
use App\Application\Invoices\Issuance\InvoiceIssuanceMapper;
use App\Application\Issuers\IssuerSecrets;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\InvoicePayload;
use Tribux\Dian\Documents\Fev19\Invoice\CoreInvoiceMapper;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceGenerationContext;

final class InvoiceIssuanceMapperTest extends TestCase
{
    public function test_it_maps_the_published_example_request(): void
    {
        $details = (new InvoiceIssuanceMapper)->fromPayload(InvoicePayload::minimal());

        self::assertSame('Cliente Fixture SAS', $details->customer->name);
        self::assertSame('800123456', $details->customer->identification);
        self::assertSame('31', $details->customer->identificationSchemeName);
        self::assertSame('05001', $details->customer->address->municipalityCode);
        self::assertSame('CO', $details->customer->address->countryCode);
        self::assertSame('1', $details->paymentMeansId);
        self::assertSame('10', $details->paymentMeansCode);
        self::assertSame('2026-08-08', $details->paymentDueDate);
        self::assertSame(['94'], $details->lineUnitCodes);
    }

    public function test_it_preserves_the_offset_the_client_declared(): void
    {
        $details = (new InvoiceIssuanceMapper)->fromPayload(InvoicePayload::minimal());

        self::assertSame('10:30:00-05:00', $details->issuedAt->format('H:i:sP'));
    }

    public function test_it_rejects_an_issue_time_without_an_offset(): void
    {
        $payload = InvoicePayload::minimal();
        $payload['issued_at'] = '2026-08-08T10:30:00';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('explicit UTC offset');

        (new InvoiceIssuanceMapper)->fromPayload($payload);
    }

    public function test_it_requires_a_unit_code_for_every_line(): void
    {
        $payload = InvoicePayload::minimal();
        unset($payload['lines'][0]['unit_code']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lines.0.unit_code must be a string');

        (new InvoiceIssuanceMapper)->fromPayload($payload);
    }

    public function test_it_names_a_missing_customer_address_field(): void
    {
        $payload = InvoicePayload::minimal();
        unset($payload['customer']['address']['department_code']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customer.address.department_code must be a string');

        (new InvoiceIssuanceMapper)->fromPayload($payload);
    }

    public function test_the_country_defaults_only_where_the_specification_allows_it(): void
    {
        $payload = InvoicePayload::minimal();
        unset($payload['customer']['address']['country_code'], $payload['customer']['address']['country_name']);

        $address = (new InvoiceIssuanceMapper)->fromPayload($payload)->customer->address;

        self::assertSame('CO', $address->countryCode);
        self::assertSame('Colombia', $address->countryName);
    }

    public function test_the_domain_invoice_carries_a_generic_address(): void
    {
        $invoice = (new InvoiceMapper)->fromArray(
            'invoice-contract-fixture',
            new DateTimeImmutable('2026-08-08T10:30:00-05:00'),
            InvoicePayload::minimal(),
        );

        self::assertNotNull($invoice->customer->address);
        self::assertSame('Medellín', $invoice->customer->address->city);
        self::assertSame('CO', $invoice->customer->address->countryCode);
        self::assertNull($invoice->number, 'A number is reserved later, not taken from the minimal request.');
    }

    public function test_the_public_contract_carries_everything_the_fev_19_mapper_needs(): void
    {
        $payload = InvoicePayload::minimal();
        // Stands in for the numbering reservation of P0.5, the only remaining
        // input the request does not carry.
        $payload['number'] = 'SETP1';

        $profile = (new JsonFileIssuerProfileProvider(__DIR__.'/../../../../examples/issuer.habilitation.json'))
            ->get('issuer_demo');
        $details = (new InvoiceIssuanceMapper)->fromPayload($payload);
        $secrets = new IssuerSecrets('12345', 'fixture-technical-key');

        $invoice = (new InvoiceMapper)->fromArray(
            'invoice-contract-fixture',
            new DateTimeImmutable('2026-08-08T10:00:00-05:00'),
            $payload,
        );

        $document = (new CoreInvoiceMapper)->map($invoice, new InvoiceGenerationContext(
            issuerReference: $profile->reference,
            environment: $profile->environment,
            control: $profile->control,
            softwareCredentials: $profile->software->withPin($secrets->softwarePin()),
            supplier: $profile->supplier,
            customer: $details->customer,
            customizationId: $profile->customizationId,
            invoiceTypeCode: $profile->invoiceTypeCode,
            issuedAt: $details->issuedAt,
            paymentMeansId: $details->paymentMeansId,
            paymentMeansCode: $details->paymentMeansCode,
            paymentDueDate: $details->paymentDueDate,
            lineUnitCodes: $details->lineUnitCodes,
            taxMappings: $profile->taxMappings,
            calculationPolicy: $profile->calculationPolicy,
            technicalKey: $secrets->technicalKey(),
        ));

        self::assertSame('SETP1', $document->invoiceNumber);
        self::assertSame('94', $document->lines[0]->unitCode);
        self::assertSame('119000.00', $document->totals->payableAmount);
        self::assertSame('01', $document->taxes[0]->subtotals[0]->taxSchemeId);
        self::assertSame(96, strlen($document->cufe));
    }
}

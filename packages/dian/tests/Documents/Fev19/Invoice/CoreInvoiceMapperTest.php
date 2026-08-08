<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Documents\Fev19\Invoice;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Core\Invoice\Calculation\InvoiceCalculationPolicy;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceId;
use Tribux\Core\Invoice\InvoiceLine as CoreInvoiceLine;
use Tribux\Core\Money\Money;
use Tribux\Core\Party\Party;
use Tribux\Core\Party\TaxIdentifier;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\Tax;
use Tribux\Core\Tax\TaxRate;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Documents\Fev19\Invoice\CoreInvoiceMapper;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceAddress;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceControl;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceGenerationContext;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceSoftwareCredentials;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxSchemeMapping;
use Tribux\Dian\Documents\Fev19\Invoice\UnsignedInvoiceXmlGenerator;
use Tribux\Dian\Validation\DianXsdValidator;

final class CoreInvoiceMapperTest extends TestCase
{
    #[Test]
    public function it_maps_calculates_and_enriches_the_initial_core_invoice_profile(): void
    {
        $document = (new CoreInvoiceMapper())->map($this->invoice(), $this->context());

        self::assertSame('SETP1', $document->invoiceNumber);
        self::assertSame(
            'a4fc014b42bb99dfe64c46aa21eb319800ddbe8e75d7ad94fb9f128ec6f3151c6e832c37bfe42240b930dc41b66cc61d',
            $document->cufe,
        );
        self::assertSame('100000.00', $document->totals->lineExtensionAmount);
        self::assertSame('119000.00', $document->totals->payableAmount);
        self::assertSame('19000.00', $document->taxes[0]->taxAmount);
        self::assertSame('01', $document->taxes[0]->subtotals[0]->taxSchemeId);
        self::assertSame('94', $document->lines[0]->unitCode);
        self::assertSame(
            'd57afa74725d6766c300677799962d0fec2ef422ac71733dd74a4884e02d0ab8cff8451726e5020450265d049dd6a6ba',
            $document->softwareProvider->securityCode,
        );
    }

    #[Test]
    public function it_rejects_an_unmapped_core_tax_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No DIAN FEV 1.9 tax mapping');

        (new CoreInvoiceMapper())->map(
            $this->invoice(),
            $this->context([new InvoiceTaxSchemeMapping('OTHER', '01', 'IVA')]),
        );
    }

    #[Test]
    public function it_requires_the_reserved_invoice_number(): void
    {
        $invoice = $this->invoice();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved invoice number');

        (new CoreInvoiceMapper())->map(new Invoice(
            id: $invoice->id,
            issuerId: $invoice->issuerId,
            customer: $invoice->customer,
            lines: $invoice->lines,
            createdAt: $invoice->createdAt,
        ), $this->context());
    }

    #[Test]
    public function its_mapped_xml_passes_the_official_xsd_when_the_toolbox_is_available(): void
    {
        $toolbox = getenv('TRIBUX_FEV19_TOOLBOX');

        if (! is_string($toolbox) || $toolbox === '') {
            self::markTestSkipped('Set TRIBUX_FEV19_TOOLBOX to run the mapped invoice XSD compliance test.');
        }

        $document = (new CoreInvoiceMapper())->map($this->invoice(), $this->context());
        $result = (new DianXsdValidator())->validate(
            (new UnsignedInvoiceXmlGenerator())->generate($document),
            Fev19ArtifactSet::discover($toolbox)->xsdFor(DianDocumentType::Invoice),
        );

        self::assertTrue($result->valid, implode("\n", array_map(
            static fn ($error): string => sprintf('line %d: %s', $error->line, $error->message),
            $result->errors,
        )));
    }

    private function invoice(): Invoice
    {
        return new Invoice(
            id: new InvoiceId('invoice-mapper-fixture'),
            issuerId: 'issuer-fixture',
            customer: new Party(
                new TaxIdentifier('800123456', 'NIT'),
                'Cliente Fixture SAS',
                'compras@example.test',
            ),
            lines: [new CoreInvoiceLine(
                description: 'Servicio & soporte <mensual>',
                quantity: new Quantity('1.00'),
                unitPrice: new Money('100000.00', 'COP'),
                taxes: [new Tax('VAT', new TaxRate('19.00'))],
            )],
            createdAt: new DateTimeImmutable('2026-08-08T10:00:00-05:00'),
            number: 'SETP1',
        );
    }

    /** @param non-empty-list<InvoiceTaxSchemeMapping>|null $taxMappings */
    private function context(?array $taxMappings = null): InvoiceGenerationContext
    {
        return new InvoiceGenerationContext(
            issuerReference: 'issuer-fixture',
            environment: DianEnvironment::Habilitation,
            control: new InvoiceControl(
                authorization: '18760000001',
                authorizationStartDate: '2026-01-01',
                authorizationEndDate: '2027-12-31',
                prefix: 'SETP',
                from: '1',
                to: '5000000',
            ),
            softwareCredentials: new InvoiceSoftwareCredentials(
                taxId: '900123456',
                verificationDigit: '8',
                identificationSchemeName: '31',
                softwareId: '00000000-0000-4000-8000-000000000001',
                pin: '12345',
            ),
            supplier: $this->party(
                'Proveedor Fixture SAS',
                '900123456',
                '8',
                'SETP',
                '11001',
                'Bogotá, D.C.',
                'Bogotá',
                '11',
            ),
            customer: $this->party(
                'Cliente Fixture SAS',
                '800123456',
                '5',
                null,
                '05001',
                'Medellín',
                'Antioquia',
                '05',
            ),
            customizationId: '05',
            invoiceTypeCode: '01',
            issuedAt: new DateTimeImmutable('2026-08-08T10:30:00-05:00'),
            paymentMeansId: '1',
            paymentMeansCode: '10',
            paymentDueDate: '2026-08-08',
            lineUnitCodes: ['94'],
            taxMappings: $taxMappings ?? [new InvoiceTaxSchemeMapping('VAT', '01', 'IVA')],
            calculationPolicy: new InvoiceCalculationPolicy(2, DecimalRoundingMode::HalfUp),
            technicalKey: 'fixture-technical-key',
        );
    }

    private function party(
        string $name,
        string $identification,
        string $verificationDigit,
        ?string $prefix,
        string $municipalityCode,
        string $city,
        string $department,
        string $departmentCode,
    ): InvoiceParty {
        return new InvoiceParty(
            accountTypeCode: '1',
            name: $name,
            identification: $identification,
            verificationDigit: $verificationDigit,
            identificationSchemeName: '31',
            taxLevelCode: 'O-48',
            taxLevelListName: '48',
            taxSchemeId: '01',
            taxSchemeName: 'IVA',
            address: new InvoiceAddress(
                municipalityCode: $municipalityCode,
                cityName: $city,
                departmentName: $department,
                departmentCode: $departmentCode,
                line: 'Dirección sintética',
            ),
            registrationPrefix: $prefix,
            email: 'fixture@example.test',
        );
    }
}

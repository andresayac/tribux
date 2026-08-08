<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Documents\Fev19\Invoice;

use DOMDocument;
use DOMXPath;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceAddress;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceControl;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceDocument;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceLine;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceMonetaryTotal;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxTotal;
use Tribux\Dian\Documents\Fev19\Invoice\SoftwareProvider;
use Tribux\Dian\Documents\Fev19\Invoice\UnsignedInvoiceXmlGenerator;
use Tribux\Dian\Validation\DianXsdValidator;

final class UnsignedInvoiceXmlGeneratorTest extends TestCase
{
    private const string NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const string NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const string NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const string NS_STS = 'dian:gov:co:facturaelectronica:Structures-2-1';

    #[Test]
    public function it_generates_a_deterministic_unsigned_ubl_invoice(): void
    {
        $invoice = $this->invoiceFromFixture();
        $generator = new UnsignedInvoiceXmlGenerator();
        $xml = $generator->generate($invoice);

        self::assertSame($xml, $generator->generate($invoice));

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));
        self::assertSame(self::NS_INVOICE, $document->documentElement?->namespaceURI);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('inv', self::NS_INVOICE);
        $xpath->registerNamespace('cbc', self::NS_CBC);
        $xpath->registerNamespace('ext', self::NS_EXT);
        $xpath->registerNamespace('sts', self::NS_STS);

        self::assertSame('1', $xpath->evaluate('string(/inv:Invoice/cbc:LineCountNumeric)'));
        self::assertSame(InvoiceDocument::PROFILE_ID, $xpath->evaluate('string(/inv:Invoice/cbc:ProfileID)'));
        self::assertSame('2', $xpath->evaluate('string(/inv:Invoice/cbc:ProfileExecutionID)'));
        self::assertSame('1', $xpath->evaluate('string(count(/inv:Invoice/ext:UBLExtensions/ext:UBLExtension))'));
        self::assertSame(
            'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.$invoice->cufe,
            $xpath->evaluate('string(/inv:Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:QRCode)'),
        );
        self::assertSame(
            'Servicio & soporte <mensual>',
            $xpath->evaluate('string(/inv:Invoice/*[local-name()="InvoiceLine"]/*[local-name()="Item"]/cbc:Description)'),
        );
        self::assertStringNotContainsString('<ds:Signature', $xml);
    }

    #[Test]
    public function it_passes_the_official_fev_1_9_invoice_xsd_when_the_toolbox_is_available(): void
    {
        $toolbox = getenv('TRIBUX_FEV19_TOOLBOX');

        if (! is_string($toolbox) || $toolbox === '') {
            self::markTestSkipped('Set TRIBUX_FEV19_TOOLBOX to run the official FEV 1.9 XSD compliance test.');
        }

        $artifacts = Fev19ArtifactSet::discover($toolbox);
        $result = (new DianXsdValidator())->validate(
            (new UnsignedInvoiceXmlGenerator())->generate($this->invoiceFromFixture()),
            $artifacts->xsdFor(DianDocumentType::Invoice),
        );

        self::assertTrue($result->valid, implode("\n", array_map(
            static fn ($error): string => sprintf('line %d: %s', $error->line, $error->message),
            $result->errors,
        )));
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $json = file_get_contents(__DIR__.'/../../../Fixtures/fev-1.9/invoice/minimal-priced-line.json');
        self::assertIsString($json);

        try {
            $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($fixture);

        return $fixture;
    }

    private function invoiceFromFixture(): InvoiceDocument
    {
        $fixture = $this->fixture();
        $control = $this->arrayAt($fixture, 'control');
        $software = $this->arrayAt($fixture, 'software');
        $payment = $this->arrayAt($fixture, 'payment');
        $totals = $this->arrayAt($fixture, 'totals');
        $taxes = $this->taxesAt($fixture, 'taxes');
        $lines = $this->arrayAt($fixture, 'lines');

        return new InvoiceDocument(
            environment: DianEnvironment::from((string) $fixture['environment']),
            control: new InvoiceControl(
                authorization: (string) $control['authorization'],
                authorizationStartDate: (string) $control['authorization_start_date'],
                authorizationEndDate: (string) $control['authorization_end_date'],
                prefix: (string) $control['prefix'],
                from: (string) $control['from'],
                to: (string) $control['to'],
            ),
            softwareProvider: new SoftwareProvider(
                taxId: (string) $software['tax_id'],
                verificationDigit: (string) $software['verification_digit'],
                identificationSchemeName: (string) $software['identification_scheme_name'],
                softwareId: (string) $software['software_id'],
                securityCode: (string) $software['security_code'],
            ),
            customizationId: (string) $fixture['customization_id'],
            invoiceNumber: (string) $fixture['invoice_number'],
            cufe: (string) $fixture['cufe'],
            issueDate: (string) $fixture['issue_date'],
            issueTime: (string) $fixture['issue_time'],
            invoiceTypeCode: (string) $fixture['invoice_type_code'],
            currency: (string) $fixture['currency'],
            supplier: $this->partyAt($fixture, 'supplier'),
            customer: $this->partyAt($fixture, 'customer'),
            paymentMeansId: (string) $payment['id'],
            paymentMeansCode: (string) $payment['means_code'],
            paymentDueDate: (string) $payment['due_date'],
            taxes: $taxes,
            totals: new InvoiceMonetaryTotal(
                lineExtensionAmount: (string) $totals['line_extension_amount'],
                taxExclusiveAmount: (string) $totals['tax_exclusive_amount'],
                taxInclusiveAmount: (string) $totals['tax_inclusive_amount'],
                payableAmount: (string) $totals['payable_amount'],
            ),
            lines: array_map(fn (mixed $line): InvoiceLine => $this->line($line), $lines),
        );
    }

    /** @param array<string, mixed> $fixture */
    private function partyAt(array $fixture, string $key): InvoiceParty
    {
        $party = $this->arrayAt($fixture, $key);
        $address = $this->arrayAt($party, 'address');

        return new InvoiceParty(
            accountTypeCode: (string) $party['account_type_code'],
            name: (string) $party['name'],
            identification: (string) $party['identification'],
            verificationDigit: (string) $party['verification_digit'],
            identificationSchemeName: (string) $party['identification_scheme_name'],
            taxLevelCode: (string) $party['tax_level_code'],
            taxLevelListName: (string) $party['tax_level_list_name'],
            taxSchemeId: (string) $party['tax_scheme_id'],
            taxSchemeName: (string) $party['tax_scheme_name'],
            address: new InvoiceAddress(
                municipalityCode: (string) $address['municipality_code'],
                cityName: (string) $address['city_name'],
                departmentName: (string) $address['department_name'],
                departmentCode: (string) $address['department_code'],
                line: (string) $address['line'],
                countryCode: (string) $address['country_code'],
                countryName: (string) $address['country_name'],
                postalZone: is_string($address['postal_zone']) ? $address['postal_zone'] : null,
            ),
            registrationPrefix: is_string($party['registration_prefix']) ? $party['registration_prefix'] : null,
            telephone: is_string($party['telephone']) ? $party['telephone'] : null,
            email: is_string($party['email']) ? $party['email'] : null,
        );
    }

    private function line(mixed $value): InvoiceLine
    {
        self::assertIsArray($value);

        return new InvoiceLine(
            id: (string) $value['id'],
            quantity: (string) $value['quantity'],
            unitCode: (string) $value['unit_code'],
            lineExtensionAmount: (string) $value['line_extension_amount'],
            description: (string) $value['description'],
            priceAmount: (string) $value['price_amount'],
            baseQuantity: (string) $value['base_quantity'],
            freeOfCharge: (bool) $value['free_of_charge'],
            taxes: $this->taxesAt($value, 'taxes'),
        );
    }

    /** @param array<string, mixed> $value
     *  @return list<InvoiceTaxTotal>
     */
    private function taxesAt(array $value, string $key): array
    {
        $taxes = $this->arrayAt($value, $key);

        return array_map(static function (mixed $tax): InvoiceTaxTotal {
            self::assertIsArray($tax);

            return new InvoiceTaxTotal(
                taxableAmount: (string) $tax['taxable_amount'],
                taxAmount: (string) $tax['tax_amount'],
                percent: (string) $tax['percent'],
                taxSchemeId: (string) $tax['scheme_id'],
                taxSchemeName: (string) $tax['scheme_name'],
            );
        }, $taxes);
    }

    /** @param array<string, mixed> $value
     *  @return array<string, mixed>
     */
    private function arrayAt(array $value, string $key): array
    {
        $item = $value[$key] ?? null;
        self::assertIsArray($item);

        return $item;
    }
}

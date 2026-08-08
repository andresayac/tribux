<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use Tribux\Dian\Qr\DianQrUrl;

/**
 * Generates the unsigned UBL 2.1 envelope for a FEV 1.9 invoice.
 *
 * The result contains sts:DianExtensions but intentionally does not create the
 * second ext:UBLExtension reserved for ds:Signature. XSD-valid is not the same
 * as DIAN-valid; signing and Schematron validation are separate stages.
 */
final class UnsignedInvoiceXmlGenerator
{
    private const string NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const string NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const string NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const string NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const string NS_STS = 'dian:gov:co:facturaelectronica:Structures-2-1';
    private const string NS_XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const string DIAN_AGENCY_ID = '195';
    private const string DIAN_AGENCY_NAME = 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)';

    public function __construct(private readonly DianQrUrl $qrUrl = new DianQrUrl())
    {
    }

    public function generate(InvoiceDocument $invoice): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;

        $root = $document->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:sts', self::NS_STS);
        $document->appendChild($root);

        $this->appendDianExtensions($root, $invoice);
        $this->append($root, self::NS_CBC, 'cbc:UBLVersionID', 'UBL 2.1');
        $this->append($root, self::NS_CBC, 'cbc:CustomizationID', $invoice->customizationId);
        $this->append($root, self::NS_CBC, 'cbc:ProfileID', InvoiceDocument::PROFILE_ID);
        $this->append($root, self::NS_CBC, 'cbc:ProfileExecutionID', $invoice->environment->profileExecutionId());
        $this->append($root, self::NS_CBC, 'cbc:ID', $invoice->invoiceNumber);
        $this->append($root, self::NS_CBC, 'cbc:UUID', $invoice->cufe, [
            'schemeID' => $invoice->environment->profileExecutionId(),
            'schemeName' => 'CUFE-SHA384',
        ]);
        $this->append($root, self::NS_CBC, 'cbc:IssueDate', $invoice->issueDate);
        $this->append($root, self::NS_CBC, 'cbc:IssueTime', $invoice->issueTime);
        $this->append($root, self::NS_CBC, 'cbc:InvoiceTypeCode', $invoice->invoiceTypeCode);
        $this->append($root, self::NS_CBC, 'cbc:DocumentCurrencyCode', $invoice->currency, [
            'listAgencyID' => '6',
            'listAgencyName' => 'United Nations Economic Commission for Europe',
            'listID' => 'ISO 4217 Alpha',
        ]);
        $this->append($root, self::NS_CBC, 'cbc:LineCountNumeric', (string) count($invoice->lines));

        $this->appendParty($root, 'cac:AccountingSupplierParty', $invoice->supplier);
        $this->appendParty($root, 'cac:AccountingCustomerParty', $invoice->customer);
        $this->appendPaymentMeans($root, $invoice);

        foreach ($invoice->taxes as $tax) {
            $this->appendTaxTotal($root, $tax, $invoice->currency);
        }

        $this->appendMonetaryTotal($root, $invoice->totals, $invoice->currency);

        foreach ($invoice->lines as $line) {
            $this->appendInvoiceLine($root, $line, $invoice->currency);
        }

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Could not serialize the FEV 1.9 invoice XML.');
        }

        return $xml;
    }

    private function appendDianExtensions(DOMElement $root, InvoiceDocument $invoice): void
    {
        $extensions = $this->append($root, self::NS_EXT, 'ext:UBLExtensions');
        $extension = $this->append($extensions, self::NS_EXT, 'ext:UBLExtension');
        $content = $this->append($extension, self::NS_EXT, 'ext:ExtensionContent');
        $dian = $this->append($content, self::NS_STS, 'sts:DianExtensions');
        $control = $this->append($dian, self::NS_STS, 'sts:InvoiceControl');

        $this->append($control, self::NS_STS, 'sts:InvoiceAuthorization', $invoice->control->authorization);
        $period = $this->append($control, self::NS_STS, 'sts:AuthorizationPeriod');
        $this->append($period, self::NS_CBC, 'cbc:StartDate', $invoice->control->authorizationStartDate);
        $this->append($period, self::NS_CBC, 'cbc:EndDate', $invoice->control->authorizationEndDate);
        $authorized = $this->append($control, self::NS_STS, 'sts:AuthorizedInvoices');
        $this->append($authorized, self::NS_STS, 'sts:Prefix', $invoice->control->prefix);
        $this->append($authorized, self::NS_STS, 'sts:From', $invoice->control->from);
        $this->append($authorized, self::NS_STS, 'sts:To', $invoice->control->to);

        $source = $this->append($dian, self::NS_STS, 'sts:InvoiceSource');
        $this->append($source, self::NS_CBC, 'cbc:IdentificationCode', 'CO', [
            'listAgencyID' => '6',
            'listAgencyName' => 'United Nations Economic Commission for Europe',
            'listSchemeURI' => 'urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1',
        ]);

        $software = $this->append($dian, self::NS_STS, 'sts:SoftwareProvider');
        $this->append($software, self::NS_STS, 'sts:ProviderID', $invoice->softwareProvider->taxId, [
            ...$this->dianAgencyAttributes(),
            'schemeID' => $invoice->softwareProvider->verificationDigit,
            'schemeName' => $invoice->softwareProvider->identificationSchemeName,
        ]);
        $this->append($software, self::NS_STS, 'sts:SoftwareID', $invoice->softwareProvider->softwareId, $this->dianAgencyAttributes());
        $this->append(
            $dian,
            self::NS_STS,
            'sts:SoftwareSecurityCode',
            $invoice->softwareProvider->securityCode,
            $this->dianAgencyAttributes(),
        );

        $authorizationProvider = $this->append($dian, self::NS_STS, 'sts:AuthorizationProvider');
        $this->append($authorizationProvider, self::NS_STS, 'sts:AuthorizationProviderID', '800197268', [
            ...$this->dianAgencyAttributes(),
            'schemeID' => '4',
            'schemeName' => '31',
        ]);
        $this->append(
            $dian,
            self::NS_STS,
            'sts:QRCode',
            $this->qrUrl->forDocumentKey($invoice->environment, $invoice->cufe),
        );
    }

    private function appendParty(DOMElement $root, string $containerName, InvoiceParty $value): void
    {
        $container = $this->append($root, self::NS_CAC, $containerName);
        $this->append($container, self::NS_CBC, 'cbc:AdditionalAccountID', $value->accountTypeCode);
        $party = $this->append($container, self::NS_CAC, 'cac:Party');
        $partyName = $this->append($party, self::NS_CAC, 'cac:PartyName');
        $this->append($partyName, self::NS_CBC, 'cbc:Name', $value->name);

        $physicalLocation = $this->append($party, self::NS_CAC, 'cac:PhysicalLocation');
        $this->appendAddress($physicalLocation, 'cac:Address', $value->address);

        $partyTaxScheme = $this->append($party, self::NS_CAC, 'cac:PartyTaxScheme');
        $this->append($partyTaxScheme, self::NS_CBC, 'cbc:RegistrationName', $value->name);
        $this->appendPartyIdentifier($partyTaxScheme, $value);
        $this->append($partyTaxScheme, self::NS_CBC, 'cbc:TaxLevelCode', $value->taxLevelCode, [
            'listName' => $value->taxLevelListName,
        ]);
        $this->appendAddress($partyTaxScheme, 'cac:RegistrationAddress', $value->address);
        $taxScheme = $this->append($partyTaxScheme, self::NS_CAC, 'cac:TaxScheme');
        $this->append($taxScheme, self::NS_CBC, 'cbc:ID', $value->taxSchemeId);
        $this->append($taxScheme, self::NS_CBC, 'cbc:Name', $value->taxSchemeName);

        $legalEntity = $this->append($party, self::NS_CAC, 'cac:PartyLegalEntity');
        $this->append($legalEntity, self::NS_CBC, 'cbc:RegistrationName', $value->name);
        $this->appendPartyIdentifier($legalEntity, $value);

        if ($value->registrationPrefix !== null) {
            $registration = $this->append($legalEntity, self::NS_CAC, 'cac:CorporateRegistrationScheme');
            $this->append($registration, self::NS_CBC, 'cbc:ID', $value->registrationPrefix);
        }

        if ($value->telephone !== null || $value->email !== null) {
            $contact = $this->append($party, self::NS_CAC, 'cac:Contact');

            if ($value->telephone !== null) {
                $this->append($contact, self::NS_CBC, 'cbc:Telephone', $value->telephone);
            }

            if ($value->email !== null) {
                $this->append($contact, self::NS_CBC, 'cbc:ElectronicMail', $value->email);
            }
        }
    }

    private function appendPartyIdentifier(DOMElement $parent, InvoiceParty $party): void
    {
        $this->append($parent, self::NS_CBC, 'cbc:CompanyID', $party->identification, [
            ...$this->dianAgencyAttributes(),
            'schemeID' => $party->verificationDigit,
            'schemeName' => $party->identificationSchemeName,
        ]);
    }

    private function appendAddress(DOMElement $parent, string $containerName, InvoiceAddress $value): void
    {
        $address = $this->append($parent, self::NS_CAC, $containerName);
        $this->append($address, self::NS_CBC, 'cbc:ID', $value->municipalityCode);
        $this->append($address, self::NS_CBC, 'cbc:CityName', $value->cityName);

        if ($value->postalZone !== null) {
            $this->append($address, self::NS_CBC, 'cbc:PostalZone', $value->postalZone);
        }

        $this->append($address, self::NS_CBC, 'cbc:CountrySubentity', $value->departmentName);
        $this->append($address, self::NS_CBC, 'cbc:CountrySubentityCode', $value->departmentCode);
        $addressLine = $this->append($address, self::NS_CAC, 'cac:AddressLine');
        $this->append($addressLine, self::NS_CBC, 'cbc:Line', $value->line);
        $country = $this->append($address, self::NS_CAC, 'cac:Country');
        $this->append($country, self::NS_CBC, 'cbc:IdentificationCode', $value->countryCode);
        $this->append($country, self::NS_CBC, 'cbc:Name', $value->countryName, ['languageID' => 'es']);
    }

    private function appendPaymentMeans(DOMElement $root, InvoiceDocument $invoice): void
    {
        $paymentMeans = $this->append($root, self::NS_CAC, 'cac:PaymentMeans');
        $this->append($paymentMeans, self::NS_CBC, 'cbc:ID', $invoice->paymentMeansId);
        $this->append($paymentMeans, self::NS_CBC, 'cbc:PaymentMeansCode', $invoice->paymentMeansCode);
        $this->append($paymentMeans, self::NS_CBC, 'cbc:PaymentDueDate', $invoice->paymentDueDate);
    }

    private function appendTaxTotal(DOMElement $parent, InvoiceTaxTotal $value, string $currency): void
    {
        $total = $this->append($parent, self::NS_CAC, 'cac:TaxTotal');
        $this->appendMoney($total, 'cbc:TaxAmount', $value->taxAmount, $currency);

        foreach ($value->subtotals as $subtotalValue) {
            $subtotal = $this->append($total, self::NS_CAC, 'cac:TaxSubtotal');
            $this->appendMoney($subtotal, 'cbc:TaxableAmount', $subtotalValue->taxableAmount, $currency);
            $this->appendMoney($subtotal, 'cbc:TaxAmount', $subtotalValue->taxAmount, $currency);
            $category = $this->append($subtotal, self::NS_CAC, 'cac:TaxCategory');
            $this->append($category, self::NS_CBC, 'cbc:Percent', $subtotalValue->percent);
            $scheme = $this->append($category, self::NS_CAC, 'cac:TaxScheme');
            $this->append($scheme, self::NS_CBC, 'cbc:ID', $subtotalValue->taxSchemeId);
            $this->append($scheme, self::NS_CBC, 'cbc:Name', $subtotalValue->taxSchemeName);
        }
    }

    private function appendMonetaryTotal(DOMElement $root, InvoiceMonetaryTotal $value, string $currency): void
    {
        $total = $this->append($root, self::NS_CAC, 'cac:LegalMonetaryTotal');
        $this->appendMoney($total, 'cbc:LineExtensionAmount', $value->lineExtensionAmount, $currency);
        $this->appendMoney($total, 'cbc:TaxExclusiveAmount', $value->taxExclusiveAmount, $currency);
        $this->appendMoney($total, 'cbc:TaxInclusiveAmount', $value->taxInclusiveAmount, $currency);
        $this->appendMoney($total, 'cbc:PayableAmount', $value->payableAmount, $currency);
    }

    private function appendInvoiceLine(DOMElement $root, InvoiceLine $value, string $currency): void
    {
        $line = $this->append($root, self::NS_CAC, 'cac:InvoiceLine');
        $this->append($line, self::NS_CBC, 'cbc:ID', $value->id);
        $this->append($line, self::NS_CBC, 'cbc:InvoicedQuantity', $value->quantity, ['unitCode' => $value->unitCode]);
        $this->appendMoney($line, 'cbc:LineExtensionAmount', $value->lineExtensionAmount, $currency);
        $this->append($line, self::NS_CBC, 'cbc:FreeOfChargeIndicator', $value->freeOfCharge ? 'true' : 'false');

        foreach ($value->taxes as $tax) {
            $this->appendTaxTotal($line, $tax, $currency);
        }

        $item = $this->append($line, self::NS_CAC, 'cac:Item');
        $this->append($item, self::NS_CBC, 'cbc:Description', $value->description);
        $price = $this->append($line, self::NS_CAC, 'cac:Price');
        $this->appendMoney($price, 'cbc:PriceAmount', $value->priceAmount, $currency);
        $this->append($price, self::NS_CBC, 'cbc:BaseQuantity', $value->baseQuantity, ['unitCode' => $value->unitCode]);
    }

    private function appendMoney(DOMElement $parent, string $qualifiedName, string $value, string $currency): void
    {
        $this->append($parent, self::NS_CBC, $qualifiedName, $value, ['currencyID' => $currency]);
    }

    /** @return array{schemeAgencyID: string, schemeAgencyName: string} */
    private function dianAgencyAttributes(): array
    {
        return [
            'schemeAgencyID' => self::DIAN_AGENCY_ID,
            'schemeAgencyName' => self::DIAN_AGENCY_NAME,
        ];
    }

    /** @param array<string, string> $attributes */
    private function append(
        DOMNode $parent,
        string $namespace,
        string $qualifiedName,
        ?string $value = null,
        array $attributes = [],
    ): DOMElement {
        $document = $parent instanceof DOMDocument ? $parent : $parent->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('Cannot append an XML element without an owner document.');
        }

        $element = $document->createElementNS($namespace, $qualifiedName);

        if ($value !== null) {
            $element->appendChild($document->createTextNode($value));
        }

        foreach ($attributes as $name => $attributeValue) {
            $element->setAttribute($name, $attributeValue);
        }

        $parent->appendChild($element);

        return $element;
    }
}

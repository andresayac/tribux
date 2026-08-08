<?php

declare(strict_types=1);

namespace App\Application\Issuers;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Tribux\Core\Invoice\Calculation\InvoiceCalculationPolicy;
use Tribux\Core\Numbering\NumberingAuthorization;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceControl;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxSchemeMapping;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;
use Tribux\Dian\Submission\Fev19\Fev19SequenceEncoding;

/**
 * Everything Tribux needs to build a FEV 1.9 document for one issuer, minus the
 * secrets.
 *
 * The profile intentionally reuses the FEV 1.9 value objects from
 * packages/dian instead of restating a dozen fields: they already carry the
 * validation, and duplicating them would create a second source of truth. It
 * therefore becomes version-specific the day a second DIAN specification
 * lands, which ADR 0009 already anticipates.
 */
final readonly class IssuerProfile
{
    /** @var non-empty-list<InvoiceTaxSchemeMapping> */
    public array $taxMappings;

    /** @var non-empty-list<string> */
    public array $allowedUnitCodes;

    /** The authorized numbering range, derived from the DIAN control block. */
    public NumberingAuthorization $numbering;

    /**
     * @param  list<InvoiceTaxSchemeMapping>  $taxMappings
     * @param  list<string>  $allowedUnitCodes
     */
    public function __construct(
        public string $reference,
        public DianEnvironment $environment,
        public InvoiceParty $supplier,
        public InvoiceControl $control,
        public SoftwareIdentity $software,
        public string $softwareProviderCode,
        public string $customizationId,
        public string $invoiceTypeCode,
        array $taxMappings,
        array $allowedUnitCodes,
        public InvoiceCalculationPolicy $calculationPolicy,
        public DateTimeZone $timezone,
        public Fev19SequenceEncoding $fileSequenceEncoding,
        public string $credentialReference,
        public ?string $testSetId = null,
    ) {
        Fev19Value::text($reference, 'issuer.reference');
        Fev19Value::code($customizationId, 'issuer.customizationId');
        Fev19Value::code($invoiceTypeCode, 'issuer.invoiceTypeCode');
        Fev19Value::text($credentialReference, 'issuer.credentialReference');

        if (preg_match('/\A[0-9]{3}\z/D', $softwareProviderCode) !== 1) {
            throw new InvalidArgumentException('issuer.softwareProviderCode must contain exactly three digits.');
        }

        // The XML and ZIP names are built from the supplier NIT without the
        // check digit, so an unusable identification must fail at configuration
        // time rather than halfway through a submission.
        if (preg_match('/\A[0-9]{1,10}\z/D', $supplier->identification) !== 1) {
            throw new InvalidArgumentException(
                'issuer.supplier.identification must contain between one and ten digits without the check digit.',
            );
        }

        if ($calculationPolicy->moneyScale !== 2) {
            throw new InvalidArgumentException('FEV 1.9 requires a two-decimal calculation policy.');
        }

        if ($testSetId !== null) {
            Fev19Value::text($testSetId, 'issuer.testSetId');
        }

        if ($taxMappings === []) {
            throw new InvalidArgumentException('issuer.taxMappings must contain at least one mapping.');
        }

        $seen = [];
        foreach ($taxMappings as $mapping) {
            if (isset($seen[$mapping->coreTaxType])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate issuer tax mapping for core type %s.',
                    $mapping->coreTaxType,
                ));
            }

            $seen[$mapping->coreTaxType] = true;
        }

        if ($allowedUnitCodes === []) {
            throw new InvalidArgumentException('issuer.allowedUnitCodes must contain at least one code.');
        }

        foreach ($allowedUnitCodes as $unitCode) {
            Fev19Value::code($unitCode, 'issuer.allowedUnitCodes');
        }

        $this->taxMappings = $taxMappings;
        $this->allowedUnitCodes = array_values(array_unique($allowedUnitCodes));

        // Built here so an unusable range fails when the configuration loads,
        // not halfway through a submission.
        $this->numbering = new NumberingAuthorization(
            reference: $control->authorization,
            prefix: $control->prefix,
            from: (int) $control->from,
            to: (int) $control->to,
            validFrom: new DateTimeImmutable($control->authorizationStartDate, $timezone),
            validTo: new DateTimeImmutable($control->authorizationEndDate, $timezone),
        );
    }

    /** The issue moment expressed in the issuer time zone, as numbering expects. */
    public function localise(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone($this->timezone);
    }

    public function allowsUnitCode(string $unitCode): bool
    {
        return in_array($unitCode, $this->allowedUnitCodes, true);
    }

    /** @throws InvalidArgumentException when the environment needs a test set that was never configured */
    public function testSetId(): string
    {
        return $this->testSetId ?? throw new InvalidArgumentException(sprintf(
            'Issuer "%s" has no testSetId configured, which the habilitation flow requires.',
            $this->reference,
        ));
    }
}

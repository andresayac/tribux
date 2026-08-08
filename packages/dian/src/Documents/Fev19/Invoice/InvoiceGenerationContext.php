<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;
use Tribux\Core\Invoice\Calculation\InvoiceCalculationPolicy;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

/**
 * DIAN-specific data supplied by issuer configuration and the issuance use case.
 *
 * It stays outside packages/core and does not expose the technical key publicly.
 */
final readonly class InvoiceGenerationContext
{
    /** @var array<string, InvoiceTaxSchemeMapping> */
    private array $taxMappings;

    /**
     * @param non-empty-list<string> $lineUnitCodes one code per core invoice line
     * @param non-empty-list<InvoiceTaxSchemeMapping> $taxMappings
     */
    public function __construct(
        public string $issuerReference,
        public DianEnvironment $environment,
        public InvoiceControl $control,
        public InvoiceSoftwareCredentials $softwareCredentials,
        public InvoiceParty $supplier,
        public InvoiceParty $customer,
        public string $customizationId,
        public string $invoiceTypeCode,
        public DateTimeImmutable $issuedAt,
        public string $paymentMeansId,
        public string $paymentMeansCode,
        public string $paymentDueDate,
        public array $lineUnitCodes,
        array $taxMappings,
        public InvoiceCalculationPolicy $calculationPolicy,
        #[SensitiveParameter]
        private string $technicalKey,
    ) {
        Fev19Value::text($issuerReference, 'issuerReference');
        Fev19Value::code($customizationId, 'customizationId');
        Fev19Value::code($invoiceTypeCode, 'invoiceTypeCode');
        Fev19Value::code($paymentMeansId, 'paymentMeansId');
        Fev19Value::code($paymentMeansCode, 'paymentMeansCode');
        Fev19Value::date($paymentDueDate, 'paymentDueDate');
        Fev19Value::text($technicalKey, 'technicalKey');

        if ($calculationPolicy->moneyScale !== 2) {
            throw new InvalidArgumentException('FEV 1.9 invoice generation requires a two-decimal calculation policy.');
        }

        if ($lineUnitCodes === []) {
            throw new InvalidArgumentException('lineUnitCodes must contain at least one code.');
        }

        foreach ($lineUnitCodes as $unitCode) {
            Fev19Value::code($unitCode, 'lineUnitCodes');
        }

        if ($taxMappings === []) {
            throw new InvalidArgumentException('taxMappings must contain at least one mapping.');
        }

        $indexed = [];

        foreach ($taxMappings as $mapping) {
            if (! $mapping instanceof InvoiceTaxSchemeMapping) {
                throw new InvalidArgumentException('taxMappings must contain InvoiceTaxSchemeMapping values only.');
            }

            if (isset($indexed[$mapping->coreTaxType])) {
                throw new InvalidArgumentException(sprintf('Duplicate tax mapping for core type %s.', $mapping->coreTaxType));
            }

            $indexed[$mapping->coreTaxType] = $mapping;
        }

        $this->taxMappings = $indexed;
    }

    public function taxMappingFor(string $coreTaxType): InvoiceTaxSchemeMapping
    {
        return $this->taxMappings[$coreTaxType] ?? throw new InvalidArgumentException(sprintf(
            'No DIAN FEV 1.9 tax mapping configured for core tax type %s.',
            $coreTaxType,
        ));
    }

    public function technicalKey(): string
    {
        return $this->technicalKey;
    }
}

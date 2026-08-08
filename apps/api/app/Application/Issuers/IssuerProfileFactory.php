<?php

declare(strict_types=1);

namespace App\Application\Issuers;

use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Tribux\Core\Decimal\DecimalRoundingMode;
use Tribux\Core\Invoice\Calculation\InvoiceCalculationPolicy;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceAddress;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceControl;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceTaxSchemeMapping;
use Tribux\Dian\Submission\Fev19\Fev19SequenceEncoding;

/**
 * Turns a plain configuration array into a validated issuer profile.
 *
 * Errors name the exact dotted path, because the usual reader is an operator
 * editing a mounted JSON file, not a developer with a stack trace.
 */
final readonly class IssuerProfileFactory
{
    /** @param array<string, mixed> $data */
    public function fromArray(string $issuerId, array $data): IssuerProfile
    {
        $software = $this->object($data, 'software');

        return new IssuerProfile(
            reference: $issuerId,
            environment: $this->environment($data),
            supplier: $this->party($this->object($data, 'supplier'), 'supplier'),
            control: $this->control($this->object($data, 'control')),
            software: new SoftwareIdentity(
                taxId: $this->string($software, 'software.tax_id'),
                verificationDigit: $this->string($software, 'software.verification_digit'),
                identificationSchemeName: $this->string($software, 'software.identification_scheme_name'),
                softwareId: $this->string($software, 'software.software_id'),
            ),
            softwareProviderCode: $this->string($software, 'software.provider_code'),
            customizationId: $this->string($data, 'customization_id'),
            invoiceTypeCode: $this->string($data, 'invoice_type_code'),
            taxMappings: $this->taxMappings($data),
            allowedUnitCodes: $this->unitCodes($data),
            calculationPolicy: $this->calculationPolicy($this->object($data, 'calculation')),
            timezone: $this->timezone($data),
            fileSequenceEncoding: $this->fileSequenceEncoding($data),
            credentialReference: $this->string($data, 'credential_reference'),
            testSetId: $this->nullableString($data, 'test_set_id'),
        );
    }

    /** @param array<string, mixed> $data */
    private function environment(array $data): DianEnvironment
    {
        $value = $this->string($data, 'environment');

        return DianEnvironment::tryFrom($value) ?? throw new InvalidArgumentException(sprintf(
            'environment must be one of "%s", got "%s".',
            implode('", "', array_column(DianEnvironment::cases(), 'value')),
            $value,
        ));
    }

    /**
     * Required and without a default: Q-008 leaves the annex text and its own
     * example disagreeing from the tenth submission onwards, so the issuer must
     * state which reading it uses instead of inheriting a guess.
     *
     * @param  array<string, mixed>  $data
     */
    private function fileSequenceEncoding(array $data): Fev19SequenceEncoding
    {
        $value = $this->string($data, 'file_sequence_encoding');

        return Fev19SequenceEncoding::tryFrom($value) ?? throw new InvalidArgumentException(sprintf(
            'file_sequence_encoding must be one of "%s", got "%s".',
            implode('", "', array_column(Fev19SequenceEncoding::cases(), 'value')),
            $value,
        ));
    }

    /** @param array<string, mixed> $data */
    private function timezone(array $data): DateTimeZone
    {
        $value = $this->string($data, 'timezone');

        try {
            return new DateTimeZone($value);
        } catch (Exception) {
            throw new InvalidArgumentException(sprintf('timezone "%s" is not a known time zone identifier.', $value));
        }
    }

    /** @param array<string, mixed> $data */
    private function control(array $data): InvoiceControl
    {
        return new InvoiceControl(
            authorization: $this->string($data, 'control.authorization'),
            authorizationStartDate: $this->string($data, 'control.authorization_start_date'),
            authorizationEndDate: $this->string($data, 'control.authorization_end_date'),
            prefix: $this->string($data, 'control.prefix'),
            from: $this->string($data, 'control.from'),
            to: $this->string($data, 'control.to'),
        );
    }

    /** @param array<string, mixed> $data */
    private function party(array $data, string $path): InvoiceParty
    {
        return new InvoiceParty(
            accountTypeCode: $this->string($data, $path.'.account_type_code'),
            name: $this->string($data, $path.'.name'),
            identification: $this->string($data, $path.'.identification'),
            verificationDigit: $this->string($data, $path.'.verification_digit'),
            identificationSchemeName: $this->string($data, $path.'.identification_scheme_name'),
            taxLevelCode: $this->string($data, $path.'.tax_level_code'),
            taxLevelListName: $this->string($data, $path.'.tax_level_list_name'),
            taxSchemeId: $this->string($data, $path.'.tax_scheme_id'),
            taxSchemeName: $this->string($data, $path.'.tax_scheme_name'),
            address: $this->address($this->object($data, 'address', $path), $path.'.address'),
            registrationPrefix: $this->nullableString($data, $path.'.registration_prefix'),
            telephone: $this->nullableString($data, $path.'.telephone'),
            email: $this->nullableString($data, $path.'.email'),
        );
    }

    /** @param array<string, mixed> $data */
    private function address(array $data, string $path): InvoiceAddress
    {
        return new InvoiceAddress(
            municipalityCode: $this->string($data, $path.'.municipality_code'),
            cityName: $this->string($data, $path.'.city_name'),
            departmentName: $this->string($data, $path.'.department_name'),
            departmentCode: $this->string($data, $path.'.department_code'),
            line: $this->string($data, $path.'.line'),
            countryCode: $this->nullableString($data, $path.'.country_code') ?? 'CO',
            countryName: $this->nullableString($data, $path.'.country_name') ?? 'Colombia',
            postalZone: $this->nullableString($data, $path.'.postal_zone'),
        );
    }

    /** @param array<string, mixed> $data */
    private function calculationPolicy(array $data): InvoiceCalculationPolicy
    {
        $scale = $data['money_scale'] ?? null;
        if (! is_int($scale)) {
            throw new InvalidArgumentException('calculation.money_scale must be an integer.');
        }

        $mode = $this->string($data, 'calculation.rounding_mode');

        return new InvoiceCalculationPolicy(
            $scale,
            DecimalRoundingMode::tryFrom($mode) ?? throw new InvalidArgumentException(sprintf(
                'calculation.rounding_mode must be one of "%s", got "%s".',
                implode('", "', array_column(DecimalRoundingMode::cases(), 'value')),
                $mode,
            )),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<InvoiceTaxSchemeMapping>
     */
    private function taxMappings(array $data): array
    {
        $mappings = [];

        foreach ($this->list($data, 'tax_mappings') as $index => $mapping) {
            $path = sprintf('tax_mappings.%d', $index);

            if (! is_array($mapping)) {
                throw new InvalidArgumentException($path.' must be an object.');
            }

            $mappings[] = new InvoiceTaxSchemeMapping(
                coreTaxType: $this->string($mapping, $path.'.core_type'),
                dianId: $this->string($mapping, $path.'.dian_id'),
                dianName: $this->string($mapping, $path.'.dian_name'),
            );
        }

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function unitCodes(array $data): array
    {
        $codes = [];

        foreach ($this->list($data, 'allowed_unit_codes') as $index => $code) {
            if (! is_string($code)) {
                throw new InvalidArgumentException(sprintf('allowed_unit_codes.%d must be a string.', $index));
            }

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function object(array $data, string $key, ?string $parentPath = null): array
    {
        $path = $parentPath === null ? $key : $parentPath.'.'.$key;
        $value = $data[$key] ?? null;

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException($path.' must be an object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException($key.' must be an array.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $path): string
    {
        $key = $this->leaf($path);
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException($path.' must be a string.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $path): ?string
    {
        $key = $this->leaf($path);
        $value = $data[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException($path.' must be a string or null.');
        }

        return $value;
    }

    private function leaf(string $path): string
    {
        $position = strrpos($path, '.');

        return $position === false ? $path : substr($path, $position + 1);
    }
}

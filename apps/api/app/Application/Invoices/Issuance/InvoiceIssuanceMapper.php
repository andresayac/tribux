<?php

declare(strict_types=1);

namespace App\Application\Invoices\Issuance;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceAddress;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;

/**
 * Maps the stored request payload to the document-specific half of a FEV 1.9
 * generation context.
 *
 * It reads the immutable payload rather than the HTTP request, so a worker
 * rebuilding an invoice months later produces exactly what was accepted.
 */
final readonly class InvoiceIssuanceMapper
{
    /** @param array<string, mixed> $payload */
    public function fromPayload(array $payload): InvoiceIssuanceDetails
    {
        $customer = $this->object($payload, 'customer');
        $payment = $this->object($payload, 'payment');

        return new InvoiceIssuanceDetails(
            customer: $this->customer($customer),
            issuedAt: $this->issuedAt($payload),
            paymentMeansId: $this->string($payment, 'payment.means_id'),
            paymentMeansCode: $this->string($payment, 'payment.means_code'),
            paymentDueDate: $this->string($payment, 'payment.due_date'),
            lineUnitCodes: $this->lineUnitCodes($payload),
        );
    }

    /** @param array<string, mixed> $payload */
    private function issuedAt(array $payload): DateTimeImmutable
    {
        $value = $this->string($payload, 'issued_at');

        if (preg_match('/(?:[Zz]|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new InvalidArgumentException('issued_at must carry an explicit UTC offset.');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidArgumentException('issued_at is not a valid date and time.');
        }
    }

    /** @param array<string, mixed> $customer */
    private function customer(array $customer): InvoiceParty
    {
        return new InvoiceParty(
            accountTypeCode: $this->string($customer, 'customer.account_type_code'),
            name: $this->string($customer, 'customer.name'),
            identification: $this->string($customer, 'customer.identification'),
            verificationDigit: $this->string($customer, 'customer.verification_digit'),
            identificationSchemeName: $this->string($customer, 'customer.identification_scheme_name'),
            taxLevelCode: $this->string($customer, 'customer.tax_level_code'),
            taxLevelListName: $this->string($customer, 'customer.tax_level_list_name'),
            taxSchemeId: $this->string($customer, 'customer.tax_scheme_id'),
            taxSchemeName: $this->string($customer, 'customer.tax_scheme_name'),
            address: $this->address($this->object($customer, 'address', 'customer')),
            registrationPrefix: $this->nullableString($customer, 'customer.registration_prefix'),
            telephone: $this->nullableString($customer, 'customer.telephone'),
            email: $this->nullableString($customer, 'customer.email'),
        );
    }

    /** @param array<string, mixed> $address */
    private function address(array $address): InvoiceAddress
    {
        return new InvoiceAddress(
            municipalityCode: $this->string($address, 'customer.address.municipality_code'),
            cityName: $this->string($address, 'customer.address.city_name'),
            departmentName: $this->string($address, 'customer.address.department_name'),
            departmentCode: $this->string($address, 'customer.address.department_code'),
            line: $this->string($address, 'customer.address.line'),
            countryCode: $this->nullableString($address, 'customer.address.country_code') ?? 'CO',
            countryName: $this->nullableString($address, 'customer.address.country_name') ?? 'Colombia',
            postalZone: $this->nullableString($address, 'customer.address.postal_zone'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function lineUnitCodes(array $payload): array
    {
        $lines = $payload['lines'] ?? null;

        if (! is_array($lines) || ! array_is_list($lines)) {
            throw new InvalidArgumentException('lines must be a list.');
        }

        $codes = [];

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException(sprintf('lines.%d must be an object.', $index));
            }

            $code = $line['unit_code'] ?? null;

            if (! is_string($code)) {
                throw new InvalidArgumentException(sprintf('lines.%d.unit_code must be a string.', $index));
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
        $value = $data[$key] ?? null;

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(
                ($parentPath === null ? $key : $parentPath.'.'.$key).' must be an object.',
            );
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $path): string
    {
        $value = $data[$this->leaf($path)] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException($path.' must be a string.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $path): ?string
    {
        $value = $data[$this->leaf($path)] ?? null;

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

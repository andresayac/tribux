<?php

declare(strict_types=1);

namespace App\Application\Invoices;

use DateTimeImmutable;
use InvalidArgumentException;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceId;
use Tribux\Core\Invoice\InvoiceLine;
use Tribux\Core\Money\Money;
use Tribux\Core\Party\Address;
use Tribux\Core\Party\Party;
use Tribux\Core\Party\TaxIdentifier;
use Tribux\Core\Quantity\Quantity;
use Tribux\Core\Tax\Tax;
use Tribux\Core\Tax\TaxRate;

final class InvoiceMapper
{
    /** @param array<string, mixed> $payload */
    public function fromArray(string $invoiceId, DateTimeImmutable $createdAt, array $payload): Invoice
    {
        $customer = $this->arrayValue($payload, 'customer');
        $linePayloads = $this->listValue($payload, 'lines');

        $lines = array_map(fn (mixed $line): InvoiceLine => $this->mapLine($this->ensureArray($line)), $linePayloads);

        return new Invoice(
            id: new InvoiceId($invoiceId),
            issuerId: $this->stringValue($payload, 'issuer_id'),
            customer: new Party(
                taxIdentifier: new TaxIdentifier(
                    $this->stringValue($customer, 'identification'),
                    $this->nullableStringValue($customer, 'identification_type'),
                ),
                name: $this->stringValue($customer, 'name'),
                email: $this->nullableStringValue($customer, 'email'),
                address: $this->address($this->arrayValue($customer, 'address')),
            ),
            lines: $lines,
            createdAt: $createdAt,
            number: $this->nullableStringValue($payload, 'number'),
        );
    }

    /**
     * The domain keeps a generic postal address. DIAN municipality and
     * department codes stay out of the core and travel to the FEV 1.9 layer
     * through the issuance details.
     *
     * @param  array<string, mixed>  $payload
     */
    private function address(array $payload): Address
    {
        return new Address(
            line: $this->stringValue($payload, 'line'),
            city: $this->stringValue($payload, 'city_name'),
            countryCode: $this->nullableStringValue($payload, 'country_code') ?? 'CO',
            postalCode: $this->nullableStringValue($payload, 'postal_zone'),
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapLine(array $payload): InvoiceLine
    {
        $unitPrice = $this->arrayValue($payload, 'unit_price');
        $taxPayloads = isset($payload['taxes']) ? $this->listValue($payload, 'taxes') : [];

        $taxes = array_map(function (mixed $tax): Tax {
            $tax = $this->ensureArray($tax);

            return new Tax(
                $this->stringValue($tax, 'type'),
                new TaxRate($this->stringValue($tax, 'rate')),
            );
        }, $taxPayloads);

        return new InvoiceLine(
            description: $this->stringValue($payload, 'description'),
            quantity: new Quantity($this->stringValue($payload, 'quantity')),
            unitPrice: new Money(
                $this->stringValue($unitPrice, 'amount'),
                $this->stringValue($unitPrice, 'currency'),
            ),
            taxes: $taxes,
        );
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function nullableStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a string or null.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        return $this->ensureArray($payload[$key] ?? null);
    }

    /** @param array<string, mixed> $payload
     * @return list<mixed>
     */
    private function listValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a list.', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function ensureArray(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('Expected an object payload.');
        }

        return $value;
    }
}

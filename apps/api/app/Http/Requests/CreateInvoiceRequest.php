<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateInvoiceRequest extends FormRequest
{
    /** RFC 3339 with a mandatory offset: a naive local time has no legal meaning. */
    private const string OFFSET_DATE_TIME = '/^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:\d{2})$/';

    /** The character set FEV 1.9 accepts for a catalogue code. */
    private const string CODE = 'regex:/^[A-Za-z0-9._-]+$/';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }

    /**
     * DIAN catalogue values are validated by shape only. Checking them against
     * the official value lists is catalogue work (Q-004); rejecting a valid
     * code today because Tribux has not inventoried the list yet would be worse
     * than accepting it and preserving it verbatim.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'issuer_id' => ['required', 'string', 'max:100'],
            'number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'issued_at' => ['required', 'string', 'regex:'.self::OFFSET_DATE_TIME, 'date'],
            'customer' => ['required', 'array'],
            'customer.identification' => ['required', 'string', 'max:100'],
            'customer.verification_digit' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:2'],
            'customer.identification_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'customer.identification_scheme_name' => ['required', 'string', self::CODE, 'max:50'],
            'customer.account_type_code' => ['required', 'string', self::CODE, 'max:50'],
            'customer.tax_level_code' => ['required', 'string', 'max:100'],
            'customer.tax_level_list_name' => ['required', 'string', self::CODE, 'max:50'],
            'customer.tax_scheme_id' => ['required', 'string', self::CODE, 'max:50'],
            'customer.tax_scheme_name' => ['required', 'string', 'max:100'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'customer.telephone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'customer.registration_prefix' => ['sometimes', 'nullable', 'string', 'max:50'],
            'customer.address' => ['required', 'array'],
            'customer.address.line' => ['required', 'string', 'max:300'],
            'customer.address.city_name' => ['required', 'string', 'max:150'],
            'customer.address.municipality_code' => ['required', 'string', 'max:20'],
            'customer.address.department_name' => ['required', 'string', 'max:150'],
            'customer.address.department_code' => ['required', 'string', 'max:20'],
            'customer.address.country_code' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'customer.address.country_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'customer.address.postal_zone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'payment' => ['required', 'array'],
            'payment.means_id' => ['required', 'string', self::CODE, 'max:50'],
            'payment.means_code' => ['required', 'string', self::CODE, 'max:50'],
            'payment.due_date' => ['required', 'string', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['required', 'array'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/'],
            'lines.*.unit_code' => ['required', 'string', self::CODE, 'max:50'],
            'lines.*.unit_price' => ['required', 'array'],
            'lines.*.unit_price.amount' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/'],
            'lines.*.unit_price.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'lines.*.taxes' => ['sometimes', 'array'],
            'lines.*.taxes.*.type' => ['required', 'string', 'max:50'],
            'lines.*.taxes.*.rate' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,4})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'issued_at.regex' => 'The issued at field must carry an explicit UTC offset, for example 2026-08-08T10:30:00-05:00.',
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    /** @return array<string, mixed> */
    public function invoicePayload(): array
    {
        $payload = $this->validated();
        unset($payload['idempotency_key']);

        return $payload;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateInvoiceRequest extends FormRequest
{
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

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'issuer_id' => ['required', 'string', 'max:100'],
            'number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'customer' => ['required', 'array'],
            'customer.identification' => ['required', 'string', 'max:100'],
            'customer.identification_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['required', 'array'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/'],
            'lines.*.unit_price' => ['required', 'array'],
            'lines.*.unit_price.amount' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/'],
            'lines.*.unit_price.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'lines.*.taxes' => ['sometimes', 'array'],
            'lines.*.taxes.*.type' => ['required', 'string', 'max:50'],
            'lines.*.taxes.*.rate' => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,4})?$/'],
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

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_an_invoice_for_asynchronous_processing(): void
    {
        $response = $this->postJson('/v1/invoices', $this->invoicePayload(), [
            'Idempotency-Key' => 'invoice-demo-0001',
            'X-Request-ID' => 'request-demo-0001',
        ]);

        $response
            ->assertAccepted()
            ->assertHeader('X-Request-ID', 'request-demo-0001')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('issuer_id', 'issuer_demo')
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('cufe', null)
            ->assertJsonStructure(['id', 'issuer_id', 'status', 'number', 'cufe', 'created_at']);

        $this->assertDatabaseHas('invoices', [
            'id' => $response->json('id'),
            'issuer_id' => 'issuer_demo',
            'status' => 'queued',
        ]);
    }

    public function test_it_replays_the_original_resource_for_the_same_key_and_payload(): void
    {
        $first = $this->postJson('/v1/invoices', $this->invoicePayload(), [
            'Idempotency-Key' => 'invoice-demo-replay',
        ]);

        $replay = $this->postJson('/v1/invoices', $this->invoicePayload(), [
            'Idempotency-Key' => 'invoice-demo-replay',
        ]);

        $replay
            ->assertAccepted()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('id', $first->json('id'));

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_it_rejects_reusing_a_key_with_a_different_payload(): void
    {
        $this->postJson('/v1/invoices', $this->invoicePayload(), [
            'Idempotency-Key' => 'invoice-demo-conflict',
        ])->assertAccepted();

        $changed = $this->invoicePayload();
        $changed['customer']['name'] = 'A different customer name';

        $this->postJson('/v1/invoices', $changed, [
            'Idempotency-Key' => 'invoice-demo-conflict',
        ])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_it_exposes_invoice_and_status_resources(): void
    {
        $created = $this->postJson('/v1/invoices', $this->invoicePayload(), [
            'Idempotency-Key' => 'invoice-demo-read',
        ]);
        $invoiceId = (string) $created->json('id');

        $this->getJson('/v1/invoices/'.$invoiceId)
            ->assertOk()
            ->assertJsonPath('id', $invoiceId)
            ->assertJsonPath('status', 'queued');

        $this->getJson('/v1/invoices/'.$invoiceId.'/status')
            ->assertOk()
            ->assertExactJson([
                'id' => $invoiceId,
                'status' => 'queued',
                'cufe' => null,
            ]);
    }

    public function test_validation_errors_use_problem_details_and_a_trace_id(): void
    {
        $this->postJson('/v1/invoices', [], ['X-Request-ID' => 'request-invalid'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'REQUEST_VALIDATION_FAILED')
            ->assertJsonPath('trace_id', 'request-invalid')
            ->assertJsonStructure(['type', 'title', 'status', 'code', 'trace_id', 'errors']);
    }

    public function test_unknown_invoice_returns_problem_details(): void
    {
        $this->getJson('/v1/invoices/0198d7f3-a02c-7b57-a63f-f14136007e64')
            ->assertNotFound()
            ->assertJsonPath('code', 'INVOICE_NOT_FOUND');
    }

    /** @return array<string, mixed> */
    private function invoicePayload(): array
    {
        return [
            'issuer_id' => 'issuer_demo',
            'customer' => [
                'identification' => '900123456',
                'identification_type' => 'NIT',
                'name' => 'Empresa Ejemplo SAS',
                'email' => 'facturacion@example.com',
            ],
            'lines' => [
                [
                    'description' => 'Servicio de desarrollo',
                    'quantity' => '1.00',
                    'unit_price' => [
                        'amount' => '100000.00',
                        'currency' => 'COP',
                    ],
                    'taxes' => [
                        ['type' => 'VAT', 'rate' => '19.00'],
                    ],
                ],
            ],
        ];
    }
}

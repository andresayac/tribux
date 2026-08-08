<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Invoices\Building\BuildInvoiceDocument;
use App\Application\Invoices\Building\BuildOutcome;
use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\Numbering\Contracts\InvoiceNumberReserver;
use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\ProcessingErrorCategory;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\IssuerSecrets;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use App\Infrastructure\Issuers\Secrets\InMemoryIssuerSecretProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeFev19DocumentValidator;
use Tests\Support\InvoicePayload;
use Tests\TestCase;
use Tribux\Core\Invoice\InvoiceStatus;

final class BuildInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const string ISSUERS_FILE = __DIR__.'/../../../../examples/issuer.habilitation.json';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('evidence');

        $this->app->singleton(
            IssuerProfileProvider::class,
            fn (): JsonFileIssuerProfileProvider => new JsonFileIssuerProfileProvider(self::ISSUERS_FILE),
        );
        $this->app->singleton(IssuerSecretProvider::class, fn (): InMemoryIssuerSecretProvider => new InMemoryIssuerSecretProvider([
            'habilitation-primary' => new IssuerSecrets('12345', 'fixture-technical-key'),
        ]));
        $this->useValidator(new FakeFev19DocumentValidator);
    }

    public function test_it_builds_validates_and_keeps_every_artefact(): void
    {
        $invoiceId = $this->createInvoice('build-happy');

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Built, $result->outcome);
        self::assertSame('SETP1', $result->number);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{96}\z/', (string) $result->cufe);

        $stored = $this->app->make(InvoiceRepository::class)->load($invoiceId);
        self::assertSame('SETP1', $stored?->number);
        self::assertSame($result->cufe, $stored?->cufe);

        $kinds = array_map(
            static fn ($entry): string => $entry->kind->value,
            $this->app->make(InvoiceProcessingRepository::class)->evidence($invoiceId),
        );
        self::assertSame(
            [
                EvidenceKind::UnsignedXml->value,
                EvidenceKind::XsdUnsignedResult->value,
                EvidenceKind::SchematronResult->value,
            ],
            $kinds,
        );
    }

    public function test_it_validates_the_document_it_generated(): void
    {
        $validator = new FakeFev19DocumentValidator;
        $this->useValidator($validator);
        $invoiceId = $this->createInvoice('build-validates-output');

        $this->build()->execute($invoiceId);

        $unsigned = $this->storedEvidence($invoiceId, EvidenceKind::UnsignedXml);
        self::assertSame($unsigned, $validator->schemaInput);
        self::assertSame($unsigned, $validator->rulesInput);
        self::assertStringContainsString('<cbc:ID>SETP1</cbc:ID>', (string) $unsigned);
    }

    public function test_the_attempt_closes_without_claiming_the_document_is_signed(): void
    {
        $invoiceId = $this->createInvoice('build-stops-before-signing');

        $this->build()->execute($invoiceId);

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'building']);

        $attempts = $this->app->make(InvoiceProcessingRepository::class)->attempts($invoiceId);
        self::assertCount(1, $attempts);
        self::assertFalse($attempts[0]->isOpen());
        self::assertSame('validating', $attempts[0]->stage->value);
    }

    public function test_running_again_neither_rebuilds_nor_consumes_a_second_number(): void
    {
        $invoiceId = $this->createInvoice('build-idempotent');
        $this->build()->execute($invoiceId);

        $again = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::NotClaimable, $again->outcome);
        self::assertCount(1, $this->app->make(InvoiceProcessingRepository::class)->attempts($invoiceId));
        self::assertCount(3, $this->app->make(InvoiceProcessingRepository::class)->evidence($invoiceId));
        self::assertSame('SETP1', $this->app->make(InvoiceNumberReserver::class)->find($invoiceId)?->value);
    }

    public function test_an_unconfigured_issuer_leaves_the_invoice_queued(): void
    {
        $this->app->singleton(
            IssuerProfileProvider::class,
            fn (): JsonFileIssuerProfileProvider => new JsonFileIssuerProfileProvider(null),
        );
        $invoiceId = $this->createInvoice('build-unconfigured');

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::NotConfigured, $result->outcome);
        self::assertSame(ProcessingErrorCategory::Configuration, $result->error?->category);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'queued']);
        self::assertSame([], $this->app->make(InvoiceProcessingRepository::class)->attempts($invoiceId));
    }

    public function test_a_missing_secret_is_recoverable_rather_than_terminal(): void
    {
        $this->app->singleton(
            IssuerSecretProvider::class,
            fn (): InMemoryIssuerSecretProvider => new InMemoryIssuerSecretProvider,
        );
        $invoiceId = $this->createInvoice('build-missing-secret');

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Failed, $result->outcome);
        self::assertSame(ProcessingErrorCategory::Configuration, $result->error?->category);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'retryable_failure']);
    }

    public function test_a_unit_code_the_issuer_never_enabled_stops_the_build(): void
    {
        $payload = InvoicePayload::minimal();
        $payload['lines'][0]['unit_code'] = 'KGM';
        $invoiceId = $this->createInvoice('build-unit-code', $payload);

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Failed, $result->outcome);
        self::assertSame(ProcessingErrorCategory::InputValidation, $result->error?->category);
        self::assertStringContainsString('is not enabled for this issuer', (string) $result->error?->message);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'permanent_failure']);
    }

    public function test_an_unmapped_tax_type_stops_the_build(): void
    {
        $payload = InvoicePayload::minimal();
        $payload['lines'][0]['taxes'][0]['type'] = 'INC';
        $invoiceId = $this->createInvoice('build-unmapped-tax', $payload);

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Failed, $result->outcome);
        self::assertSame(ProcessingErrorCategory::InputValidation, $result->error?->category);
        self::assertStringContainsString('No DIAN FEV 1.9 tax mapping', (string) $result->error?->message);
    }

    public function test_mixed_currencies_never_reach_the_build_stage(): void
    {
        // The domain invariant is checked when the invoice is accepted, so a
        // payload that cannot form an invoice is refused at the boundary rather
        // than consuming a number and an attempt later.
        $payload = InvoicePayload::minimal();
        $payload['lines'][1] = $payload['lines'][0];
        $payload['lines'][1]['unit_price']['currency'] = 'USD';

        $this->postJson('/v1/invoices', $payload, ['Idempotency-Key' => 'build-mixed-currency'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DOMAIN_VALIDATION_FAILED')
            ->assertJsonPath('detail', 'All invoice lines must use the same currency.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_an_invalid_schema_stops_the_build_and_keeps_the_findings(): void
    {
        $this->useValidator(FakeFev19DocumentValidator::rejectingSchema());
        $invoiceId = $this->createInvoice('build-invalid-xsd');

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Failed, $result->outcome);
        self::assertSame(ProcessingErrorCategory::LocalValidation, $result->error?->category);
        self::assertSame('XSD_UNSIGNED_INVALID', $result->error?->code);

        $stored = (string) $this->storedEvidence($invoiceId, EvidenceKind::XsdUnsignedResult);
        self::assertStringContainsString('"valid":false', $stored);
        self::assertStringContainsString("Element 'cbc:ID': missing.", $stored);

        // Schematron never ran, so no verdict is invented for it.
        self::assertNull($this->storedEvidence($invoiceId, EvidenceKind::SchematronResult));
    }

    public function test_a_schematron_finding_is_reported_and_never_declared_valid(): void
    {
        // Q-009: the annex ProfileID trips FAD03 against the v2026 stylesheet.
        // The unsigned run cannot be the gate — the official rules require
        // ds:Signature — so the finding is surfaced and preserved intact, and
        // the build never claims the document is fit to submit.
        $this->useValidator(FakeFev19DocumentValidator::reportingFad03());
        $invoiceId = $this->createInvoice('build-fad03');

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Built, $result->outcome);
        self::assertTrue($result->hasUnsignedRuleFindings());
        self::assertSame(['FAD03'], $result->unsignedRuleFindings);

        $stored = (string) $this->storedEvidence($invoiceId, EvidenceKind::SchematronResult);
        self::assertStringContainsString('"valid":false', $stored);
        self::assertStringContainsString('"rule_code":"FAD03"', $stored);
        self::assertStringContainsString('"severity":"fatal"', $stored);
        self::assertStringContainsString('ProfileID no corresponde', $stored);
        self::assertStringContainsString('"original":"Fatal:', $stored, 'The original message is kept unflattened.');
    }

    public function test_a_clean_run_reports_no_rule_findings(): void
    {
        $invoiceId = $this->createInvoice('build-clean-rules');

        $result = $this->build()->execute($invoiceId);

        self::assertFalse($result->hasUnsignedRuleFindings());
        self::assertSame([], $result->unsignedRuleFindings);
    }

    public function test_a_client_supplied_number_is_checked_and_consumed_like_any_other(): void
    {
        $payload = InvoicePayload::minimal();
        $payload['number'] = 'SETP40';
        $invoiceId = $this->createInvoice('build-supplied-number', $payload);

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Built, $result->outcome);
        self::assertSame('SETP40', $result->number);
        self::assertSame(40, $this->app->make(InvoiceNumberReserver::class)->find($invoiceId)?->ordinal);

        // A second invoice cannot reuse it.
        $other = $this->createInvoice('build-supplied-number-clash', $payload);
        $clash = $this->build()->execute($other);

        self::assertSame(BuildOutcome::Failed, $clash->outcome);
        self::assertStringContainsString('already belongs to another document', (string) $clash->error?->message);
    }

    public function test_a_number_outside_the_authorization_stops_the_build(): void
    {
        $payload = InvoicePayload::minimal();
        $payload['number'] = 'OTHER7';
        $invoiceId = $this->createInvoice('build-foreign-number', $payload);

        $result = $this->build()->execute($invoiceId);

        self::assertSame(BuildOutcome::Failed, $result->outcome);
        self::assertSame(ProcessingErrorCategory::InputValidation, $result->error?->category);
        self::assertStringContainsString('does not belong to authorization', (string) $result->error?->message);
    }

    public function test_an_unknown_invoice_is_simply_not_claimable(): void
    {
        self::assertSame(
            BuildOutcome::NotClaimable,
            $this->build()->execute('019fe2ad-0000-7000-8000-00000000dead')->outcome,
        );
    }

    public function test_the_status_history_records_only_the_transition_that_happened(): void
    {
        $invoiceId = $this->createInvoice('build-history');
        $this->build()->execute($invoiceId);

        $history = $this->app->make(InvoiceProcessingRepository::class)->history($invoiceId);

        self::assertSame(
            [InvoiceStatus::Queued, InvoiceStatus::Building],
            array_map(static fn ($change): InvoiceStatus => $change->to, $history),
        );
    }

    private function build(): BuildInvoiceDocument
    {
        return $this->app->make(BuildInvoiceDocument::class);
    }

    private function useValidator(FakeFev19DocumentValidator $validator): void
    {
        $this->app->instance(Fev19DocumentValidator::class, $validator);
    }

    private function storedEvidence(string $invoiceId, EvidenceKind $kind): ?string
    {
        foreach ($this->app->make(InvoiceProcessingRepository::class)->evidence($invoiceId) as $entry) {
            if ($entry->kind === $kind) {
                return $this->app->make(EvidenceStore::class)->get($entry->stored->reference);
            }
        }

        return null;
    }

    /** @param array<string, mixed>|null $payload */
    private function createInvoice(string $idempotencyKey, ?array $payload = null): string
    {
        return $this->app->make(CreateInvoice::class)
            ->execute($payload ?? InvoicePayload::minimal(), $idempotencyKey)
            ->invoice
            ->id;
    }
}

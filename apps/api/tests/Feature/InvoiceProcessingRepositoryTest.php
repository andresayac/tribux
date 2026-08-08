<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\AttemptNotOpen;
use App\Application\Invoices\Processing\ProcessingError;
use App\Application\Invoices\Processing\ProcessingErrorCategory;
use App\Application\Invoices\Processing\ProcessingStage;
use App\Application\Invoices\Processing\StatusChangeSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tribux\Core\Invoice\IllegalStatusTransition;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Dian\DianEnvironment;

final class InvoiceProcessingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_invoice_opens_the_audit_trail(): void
    {
        $invoiceId = $this->createInvoice('audit-trail');

        $history = $this->repository()->history($invoiceId);

        self::assertCount(1, $history);
        self::assertNull($history[0]->from);
        self::assertSame(InvoiceStatus::Queued, $history[0]->to);
        self::assertSame(StatusChangeSource::Api, $history[0]->source);
        self::assertNull($history[0]->attemptId);
    }

    public function test_claiming_a_queued_invoice_opens_the_first_attempt(): void
    {
        $invoiceId = $this->createInvoice('claim');

        $attempt = $this->repository()->claimForBuilding($invoiceId, DianEnvironment::Habilitation);

        self::assertNotNull($attempt);
        self::assertSame(1, $attempt->attemptNumber);
        self::assertSame(ProcessingStage::Building, $attempt->stage);
        self::assertSame(DianEnvironment::Habilitation, $attempt->environment);
        self::assertNull($attempt->outcome);
        self::assertTrue($attempt->isOpen());

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'building']);

        $history = $this->repository()->history($invoiceId);
        self::assertCount(2, $history);
        self::assertSame(InvoiceStatus::Queued, $history[1]->from);
        self::assertSame(InvoiceStatus::Building, $history[1]->to);
        self::assertSame(StatusChangeSource::Worker, $history[1]->source);
        self::assertSame($attempt->id, $history[1]->attemptId);
    }

    public function test_a_second_worker_cannot_claim_the_same_invoice(): void
    {
        $invoiceId = $this->createInvoice('single-owner');
        $repository = $this->repository();

        self::assertNotNull($repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation));
        self::assertNull($repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation));

        self::assertCount(1, $repository->attempts($invoiceId));
    }

    public function test_the_database_rejects_a_second_open_attempt(): void
    {
        $invoiceId = $this->createInvoice('open-attempt-index');
        $attempt = $this->repository()->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        // A savepoint keeps the surrounding test transaction usable after
        // PostgreSQL aborts the failing statement.
        DB::beginTransaction();

        try {
            DB::table('invoice_processing_attempts')->insert([
                'id' => '0198d7f3-a02c-7b57-a63f-f14136009999',
                'invoice_id' => $invoiceId,
                'attempt_number' => 2,
                'environment' => 'habilitation',
                'stage' => 'building',
                'started_at' => '2026-08-08 12:00:00+00',
                'created_at' => '2026-08-08 12:00:00+00',
                'updated_at' => '2026-08-08 12:00:00+00',
            ]);

            DB::rollBack();
            self::fail('The partial unique index must reject a second open attempt.');
        } catch (QueryException $exception) {
            DB::rollBack();
            self::assertStringContainsStringIgnoringCase('unique', $exception->getMessage());
        }

        self::assertSame(
            1,
            DB::table('invoice_processing_attempts')->whereNull('finished_at')->count(),
        );
    }

    public function test_an_invoice_outside_queued_cannot_be_claimed(): void
    {
        $invoiceId = $this->createInvoice('not-queued');
        $repository = $this->repository();

        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);
        $repository->succeed($attempt->id, InvoiceStatus::Signed);

        self::assertNull($repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation));
    }

    public function test_an_unknown_invoice_is_not_claimable(): void
    {
        self::assertNull(
            $this->repository()->claimForBuilding('0198d7f3-a02c-7b57-a63f-f14136000000', DianEnvironment::Habilitation),
        );
    }

    public function test_it_records_stages_inside_an_open_attempt(): void
    {
        $invoiceId = $this->createInvoice('stages');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        $advanced = $repository->advance($attempt->id, ProcessingStage::Signing);

        self::assertSame(ProcessingStage::Signing, $advanced->stage);
        self::assertTrue($advanced->isOpen());
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'building']);
    }

    public function test_a_closed_attempt_cannot_be_reopened(): void
    {
        $invoiceId = $this->createInvoice('closed-attempt');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);
        $repository->succeed($attempt->id, InvoiceStatus::Signed);

        $this->expectException(AttemptNotOpen::class);

        $repository->advance($attempt->id, ProcessingStage::Packaging);
    }

    public function test_an_illegal_target_status_leaves_the_attempt_open(): void
    {
        $invoiceId = $this->createInvoice('illegal-transition');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        try {
            $repository->succeed($attempt->id, InvoiceStatus::Accepted);
            self::fail('Building must not jump straight to accepted.');
        } catch (IllegalStatusTransition $exception) {
            self::assertSame(InvoiceStatus::Building, $exception->from);
            self::assertSame(InvoiceStatus::Accepted, $exception->to);
        }

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'building']);
        self::assertTrue($repository->attempts($invoiceId)[0]->isOpen());
    }

    public function test_a_submission_attempt_keeps_the_invoice_signed_until_an_outcome_is_known(): void
    {
        $invoiceId = $this->createInvoice('submission-ownership');
        $repository = $this->repository();
        $building = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($building);
        $repository->succeed($building->id, InvoiceStatus::Signed);

        self::assertNull(
            $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation),
            'A signed invoice must not be rebuilt.',
        );

        $submission = $repository->claimForSubmission($invoiceId, DianEnvironment::Habilitation);

        self::assertNotNull($submission);
        self::assertSame(2, $submission->attemptNumber);
        self::assertSame(ProcessingStage::Submitting, $submission->stage);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'signed']);
    }

    public function test_an_ambiguous_transport_failure_awaits_reconciliation_instead_of_retrying(): void
    {
        $invoiceId = $this->createInvoice('ambiguous');
        $repository = $this->repository();
        $building = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($building);
        $repository->succeed($building->id, InvoiceStatus::Signed);

        $submission = $repository->claimForSubmission($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($submission);

        $error = new ProcessingError(
            ProcessingErrorCategory::TransportAmbiguous,
            'CURLE_OPERATION_TIMEDOUT',
            'The total timeout elapsed after the request was written.',
        );
        $closed = $repository->fail($submission->id, $error, InvoiceStatus::AwaitingReconciliation);

        self::assertFalse($closed->isOpen());
        self::assertNotNull($closed->error);
        self::assertFalse($closed->error->isAutomaticallyRetryable());
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'awaiting_reconciliation']);

        self::assertNull(
            $repository->claimForSubmission($invoiceId, DianEnvironment::Habilitation),
            'An unreconciled invoice must never be resent.',
        );
        self::assertNull($repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation));
    }

    public function test_an_inconclusive_status_query_produces_evidence_but_no_verdict(): void
    {
        $invoiceId = $this->createInvoice('polling');
        $repository = $this->repository();
        $building = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($building);
        $repository->succeed($building->id, InvoiceStatus::Signed);

        $submission = $repository->claimForSubmission($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($submission);
        $repository->recordRemoteExchange($submission->id, 'SendTestSetAsync', 200, 'a4c1b2d3e4f5');
        $repository->succeed($submission->id, InvoiceStatus::Submitted);

        $poll = $repository->claimForPolling($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($poll);
        self::assertSame(ProcessingStage::Polling, $poll->stage);

        $repository->recordRemoteExchange($poll->id, 'GetStatusZip', 200, null, [
            ['code' => null, 'message' => 'En proceso'],
        ]);
        $closed = $repository->succeed($poll->id);

        self::assertFalse($closed->isOpen());
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'submitted']);

        // Polling only appends attempts; the status trail is untouched by it.
        $history = $repository->history($invoiceId);
        self::assertSame(
            [InvoiceStatus::Queued, InvoiceStatus::Building, InvoiceStatus::Signed, InvoiceStatus::Submitted],
            array_map(static fn ($change): InvoiceStatus => $change->to, $history),
        );
    }

    public function test_it_preserves_a_structured_failure(): void
    {
        $invoiceId = $this->createInvoice('failure');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        $error = new ProcessingError(
            ProcessingErrorCategory::LocalValidation,
            'FAD03',
            'Schematron reported a blocking finding.',
        );

        $failed = $repository->fail($attempt->id, $error, InvoiceStatus::PermanentFailure);

        self::assertFalse($failed->isOpen());
        self::assertNotNull($failed->error);
        self::assertSame(ProcessingErrorCategory::LocalValidation, $failed->error->category);
        self::assertSame('FAD03', $failed->error->code);
        self::assertFalse($failed->error->isAutomaticallyRetryable());
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'permanent_failure']);
    }

    public function test_a_retryable_failure_opens_a_new_numbered_attempt(): void
    {
        $invoiceId = $this->createInvoice('retry');
        $repository = $this->repository();

        $first = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($first);
        $repository->fail(
            $first->id,
            new ProcessingError(ProcessingErrorCategory::TransportSafe, 'CURLE_COULDNT_CONNECT', 'Connection refused.'),
            InvoiceStatus::RetryableFailure,
        );

        $repository->requeue($invoiceId, StatusChangeSource::Operator);
        $second = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);

        self::assertNotNull($second);
        self::assertSame(2, $second->attemptNumber);

        $attempts = $repository->attempts($invoiceId);
        self::assertCount(2, $attempts);
        self::assertNotNull($attempts[0]->error, 'The first attempt keeps its failure.');
        self::assertSame('CURLE_COULDNT_CONNECT', $attempts[0]->error->code);
        self::assertFalse($attempts[0]->isOpen());
        self::assertTrue($attempts[1]->isOpen());
    }

    public function test_it_stores_evidence_metadata_without_the_bytes(): void
    {
        $invoiceId = $this->createInvoice('evidence');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        $contents = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';
        $stored = StoredEvidence::forContents(
            'evidence/'.$invoiceId.'/unsigned.xml',
            $contents,
            'application/xml',
        );

        $entry = $repository->recordEvidence($attempt->id, EvidenceKind::UnsignedXml, $stored);

        self::assertSame(EvidenceKind::UnsignedXml, $entry->kind);
        self::assertSame(hash('sha256', $contents), $entry->stored->sha256);
        self::assertSame(strlen($contents), $entry->stored->bytes);
        self::assertSame($attempt->id, $entry->attemptId);

        $columns = array_keys((array) DB::table('invoice_evidence')->first());
        self::assertNotContains('contents', $columns);
        self::assertNotContains('payload', $columns);

        self::assertCount(1, $repository->evidence($invoiceId));
    }

    public function test_it_preserves_a_remote_exchange_and_never_clears_a_zip_key(): void
    {
        $invoiceId = $this->createInvoice('remote-exchange');
        $repository = $this->repository();
        $attempt = $repository->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        $recorded = $repository->recordRemoteExchange(
            $attempt->id,
            'SendTestSetAsync',
            200,
            'a4c1b2d3e4f5',
            [['code' => '00', 'message' => 'Procesado']],
        );

        self::assertSame('SendTestSetAsync', $recorded->operation);
        self::assertSame(200, $recorded->lastHttpStatus);
        self::assertSame('a4c1b2d3e4f5', $recorded->zipKey);
        self::assertSame([['code' => '00', 'message' => 'Procesado']], $recorded->dianMessages);

        $later = $repository->recordRemoteExchange($attempt->id, 'GetStatusZip', 500, null, []);

        self::assertSame('a4c1b2d3e4f5', $later->zipKey, 'A known ZipKey must survive a later failed query.');
        self::assertSame(500, $later->lastHttpStatus);
    }

    private function repository(): InvoiceProcessingRepository
    {
        return $this->app->make(InvoiceProcessingRepository::class);
    }

    private function createInvoice(string $idempotencyKey): string
    {
        $result = $this->app->make(CreateInvoice::class)->execute([
            'issuer_id' => 'issuer_demo',
            'customer' => [
                'identification' => '900123456',
                'identification_type' => 'NIT',
                'name' => 'Empresa Ejemplo SAS',
            ],
            'lines' => [
                [
                    'description' => 'Servicio de desarrollo',
                    'quantity' => '1.00',
                    'unit_price' => ['amount' => '100000.00', 'currency' => 'COP'],
                    'taxes' => [['type' => 'VAT', 'rate' => '19.00']],
                ],
            ],
        ], $idempotencyKey);

        return $result->invoice->id;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\EvidenceNotAllowed;
use App\Infrastructure\Evidence\DiskEvidenceStore;
use App\Infrastructure\Evidence\InMemoryEvidenceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\InvoicePayload;
use Tests\TestCase;
use Tribux\Dian\DianEnvironment;

final class EvidenceStoreTest extends TestCase
{
    use RefreshDatabase;

    private const string UNSIGNED_XML = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('evidence');
    }

    public function test_it_stores_bytes_and_returns_verifiable_metadata(): void
    {
        $stored = $this->store()->put(
            '019fe2ad-0000-7000-8000-000000000001',
            '019fe2ad-0000-7000-8000-000000000002',
            EvidenceKind::UnsignedXml,
            self::UNSIGNED_XML,
            'application/xml',
        );

        self::assertSame(hash('sha256', self::UNSIGNED_XML), $stored->sha256);
        self::assertSame(strlen(self::UNSIGNED_XML), $stored->bytes);
        self::assertSame('application/xml', $stored->mediaType);
        self::assertStringEndsWith('.xml', $stored->reference);

        Storage::disk('evidence')->assertExists($stored->reference);
        self::assertSame(self::UNSIGNED_XML, $this->store()->get($stored->reference));
    }

    public function test_the_reference_is_derived_from_the_content_so_storing_twice_is_idempotent(): void
    {
        $first = $this->store()->put('inv-1', 'att-1', EvidenceKind::SignedXml, self::UNSIGNED_XML, 'application/xml');
        $second = $this->store()->put('inv-1', 'att-1', EvidenceKind::SignedXml, self::UNSIGNED_XML, 'application/xml');
        $other = $this->store()->put('inv-1', 'att-1', EvidenceKind::SignedXml, self::UNSIGNED_XML.' ', 'application/xml');

        self::assertSame($first->reference, $second->reference);
        self::assertNotSame($first->reference, $other->reference);
    }

    public function test_a_soap_request_is_not_stored_unless_explicitly_enabled(): void
    {
        self::assertFalse($this->store()->allows(EvidenceKind::SendTestSetRequestXml));

        try {
            $this->store()->put('inv-1', 'att-1', EvidenceKind::SendTestSetRequestXml, '<Envelope/>', 'application/xml');
            self::fail('Storing a SOAP request must be opt-in.');
        } catch (EvidenceNotAllowed $exception) {
            self::assertStringContainsString('TRIBUX_EVIDENCE_STORE_SOAP_REQUESTS', $exception->getMessage());
        }

        $optedIn = new DiskEvidenceStore(Storage::disk('evidence'), storeSoapRequests: true);

        self::assertTrue($optedIn->allows(EvidenceKind::SendTestSetRequestXml));
        $optedIn->put('inv-1', 'att-1', EvidenceKind::SendTestSetRequestXml, '<Envelope/>', 'application/xml');
    }

    public function test_an_identifier_cannot_escape_the_evidence_root(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('single safe path segment');

        $this->store()->put('../../etc', 'att-1', EvidenceKind::SignedXml, self::UNSIGNED_XML, 'application/xml');
    }

    public function test_empty_evidence_is_a_bug_not_an_artefact(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->store()->put('inv-1', 'att-1', EvidenceKind::SignedXml, '', 'application/xml');
    }

    public function test_stored_bytes_and_persisted_metadata_describe_the_same_artefact(): void
    {
        $invoiceId = $this->createInvoice();
        $processing = $this->app->make(InvoiceProcessingRepository::class);
        $attempt = $processing->claimForBuilding($invoiceId, DianEnvironment::Habilitation);
        self::assertNotNull($attempt);

        $stored = $this->store()->put(
            $invoiceId,
            $attempt->id,
            EvidenceKind::UnsignedXml,
            self::UNSIGNED_XML,
            'application/xml',
        );
        $entry = $processing->recordEvidence($attempt->id, EvidenceKind::UnsignedXml, $stored);

        self::assertSame($stored->reference, $entry->stored->reference);
        self::assertSame(
            hash('sha256', $this->store()->get($entry->stored->reference)),
            $entry->stored->sha256,
        );
    }

    public function test_the_in_memory_double_behaves_like_the_disk_store(): void
    {
        $memory = new InMemoryEvidenceStore;

        $fromMemory = $memory->put('inv-1', 'att-1', EvidenceKind::SubmissionZip, 'PK-bytes', 'application/zip');
        $fromDisk = $this->store()->put('inv-1', 'att-1', EvidenceKind::SubmissionZip, 'PK-bytes', 'application/zip');

        self::assertSame($fromDisk->reference, $fromMemory->reference);
        self::assertSame($fromDisk->sha256, $fromMemory->sha256);
        self::assertSame('PK-bytes', $memory->get($fromMemory->reference));
        self::assertFalse($memory->allows(EvidenceKind::SendTestSetRequestXml));
    }

    private function store(): EvidenceStore
    {
        return $this->app->make(EvidenceStore::class);
    }

    private function createInvoice(): string
    {
        return $this->app->make(CreateInvoice::class)
            ->execute(InvoicePayload::minimal(), 'evidence-store')
            ->invoice
            ->id;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Invoices\Building\BuildInvoiceDocument;
use App\Application\Invoices\Building\BuildInvoiceDocumentResult;
use App\Application\Invoices\Building\BuildOutcome;
use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\IssuerSecrets;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use App\Infrastructure\Issuers\Secrets\InMemoryIssuerSecretProvider;
use App\Infrastructure\Validation\OfficialFev19DocumentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InvoicePayload;
use Tests\TestCase;

/**
 * Runs the pipeline against the real DIAN toolbox and SaxonJ-HE.
 *
 * Skipped unless the official artefacts are available, like the other
 * compliance integration tests, because they are downloaded and never
 * committed.
 */
final class BuildInvoiceDocumentWithOfficialArtifactsTest extends TestCase
{
    use RefreshDatabase;

    private ?BuildInvoiceDocumentResult $result = null;

    public function test_the_generated_document_satisfies_the_official_xsd(): void
    {
        $invoiceId = $this->buildWithOfficialArtifacts();

        $schemaResult = $this->decodedEvidence($invoiceId, EvidenceKind::XsdUnsignedResult);

        self::assertNotNull($schemaResult, 'The XSD result must be preserved as evidence.');
        self::assertTrue(
            $schemaResult['valid'] ?? false,
            'Unsigned XSD findings: '.json_encode($schemaResult['errors'] ?? [], JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_the_schematron_verdict_is_preserved_and_never_overridden(): void
    {
        $invoiceId = $this->buildWithOfficialArtifacts();
        $result = $this->result;

        $schematron = $this->decodedEvidence($invoiceId, EvidenceKind::SchematronResult);
        self::assertNotNull($schematron, 'The Schematron result must be preserved as evidence.');
        self::assertIsArray($schematron['messages'] ?? null);

        self::assertSame(BuildOutcome::Built, $result?->outcome);

        if ($schematron['valid'] === true) {
            self::assertFalse($result->hasUnsignedRuleFindings());

            return;
        }

        // An unsigned document always trips FAC03 because the official rules
        // require ds:Signature, so findings here are expected and reported
        // rather than hidden. Q-009 additionally shows up as FAD03.
        self::assertTrue($result->hasUnsignedRuleFindings());
        self::assertNotSame([], $schematron['messages']);
        self::assertContains('FAC03', $result->unsignedRuleFindings);
    }

    private function buildWithOfficialArtifacts(): string
    {
        $toolbox = getenv('TRIBUX_FEV19_TOOLBOX');
        $saxonHome = getenv('TRIBUX_SAXON_HOME');

        if (! is_string($toolbox) || $toolbox === '' || ! is_string($saxonHome) || $saxonHome === '') {
            self::markTestSkipped('Set TRIBUX_FEV19_TOOLBOX and TRIBUX_SAXON_HOME for the official artefact test.');
        }

        Storage::fake('evidence');

        $this->app->singleton(
            IssuerProfileProvider::class,
            fn (): JsonFileIssuerProfileProvider => new JsonFileIssuerProfileProvider(
                __DIR__.'/../../../../examples/issuer.habilitation.json',
            ),
        );
        $this->app->singleton(IssuerSecretProvider::class, fn (): InMemoryIssuerSecretProvider => new InMemoryIssuerSecretProvider([
            'habilitation-primary' => new IssuerSecrets('12345', 'fixture-technical-key'),
        ]));
        $this->app->instance(
            Fev19DocumentValidator::class,
            new OfficialFev19DocumentValidator($toolbox, $saxonHome),
        );

        $invoiceId = $this->app->make(CreateInvoice::class)
            ->execute(InvoicePayload::minimal(), 'build-official-artifacts')
            ->invoice
            ->id;

        $this->result = $this->app->make(BuildInvoiceDocument::class)->execute($invoiceId);

        return $invoiceId;
    }

    /** @return array<string, mixed>|null */
    private function decodedEvidence(string $invoiceId, EvidenceKind $kind): ?array
    {
        foreach ($this->app->make(InvoiceProcessingRepository::class)->evidence($invoiceId) as $entry) {
            if ($entry->kind !== $kind) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(
                $this->app->make(EvidenceStore::class)->get($entry->stored->reference),
                true,
                32,
                JSON_THROW_ON_ERROR,
            );

            return $decoded;
        }

        return null;
    }
}

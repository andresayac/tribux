<?php

declare(strict_types=1);

namespace App\Application\Invoices\Building;

use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Data\StoredInvoice;
use App\Application\Invoices\InvoiceMapper;
use App\Application\Invoices\Issuance\InvoiceIssuanceDetails;
use App\Application\Invoices\Issuance\InvoiceIssuanceMapper;
use App\Application\Invoices\Numbering\Contracts\InvoiceNumberReserver;
use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\ProcessingError;
use App\Application\Invoices\Processing\ProcessingErrorCategory;
use App\Application\Invoices\Processing\ProcessingStage;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\Exceptions\IssuerConfigurationInvalid;
use App\Application\Issuers\Exceptions\IssuerNotConfigured;
use App\Application\Issuers\Exceptions\SecretNotAvailable;
use App\Application\Issuers\IssuerProfile;
use App\Application\Issuers\IssuerSecrets;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Core\Numbering\AuthorizationNotActive;
use Tribux\Core\Numbering\NumberOutsideAuthorizedRange;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Documents\Fev19\Invoice\CoreInvoiceMapper;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceGenerationContext;
use Tribux\Dian\Documents\Fev19\Invoice\UnsignedInvoiceXmlGenerator;
use Tribux\Dian\Validation\Schematron\SchematronSeverity;
use Tribux\Dian\Validation\Schematron\SchematronValidationResult;

/**
 * Builds and locally validates a queued invoice. Never touches the network.
 *
 * The use case is plain PHP: a Laravel job only resolves it and passes an
 * invoice id, so the same pipeline can be driven from a command or a test.
 *
 * It deliberately stops after local validation and leaves the invoice in
 * `building`. Signing and packaging are a separate slice, and pretending the
 * document is `signed` here would let a later stage skip work that never ran.
 */
final readonly class BuildInvoiceDocument
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceProcessingRepository $processing,
        private IssuerProfileProvider $profiles,
        private IssuerSecretProvider $secrets,
        private InvoiceNumberReserver $numbers,
        private InvoiceIssuanceMapper $issuance,
        private InvoiceMapper $domain,
        private Fev19DocumentValidator $validator,
        private EvidenceStore $evidence,
        private CoreInvoiceMapper $fev19 = new CoreInvoiceMapper,
        private UnsignedInvoiceXmlGenerator $xml = new UnsignedInvoiceXmlGenerator,
    ) {}

    public function execute(string $invoiceId): BuildInvoiceDocumentResult
    {
        $stored = $this->invoices->load($invoiceId);

        if ($stored === null) {
            return BuildInvoiceDocumentResult::notClaimable();
        }

        // Resolving the profile before claiming keeps a misconfigured issuer
        // from burning an attempt number.
        try {
            $profile = $this->profiles->get($stored->issuerId);
        } catch (IssuerNotConfigured|IssuerConfigurationInvalid $exception) {
            return BuildInvoiceDocumentResult::notConfigured(new ProcessingError(
                ProcessingErrorCategory::Configuration,
                $this->codeFor($exception),
                $exception->getMessage(),
            ));
        }

        $attempt = $this->processing->claimForBuilding($invoiceId, $profile->environment);

        if ($attempt === null) {
            return BuildInvoiceDocumentResult::notClaimable();
        }

        try {
            return $this->build($stored, $profile, $attempt->id);
        } catch (Throwable $exception) {
            // A defect must not leave the invoice owned by a dead worker.
            $this->processing->fail(
                $attempt->id,
                new ProcessingError(
                    ProcessingErrorCategory::Internal,
                    'UNEXPECTED_BUILD_FAILURE',
                    'The build stage failed unexpectedly.',
                ),
                InvoiceStatus::RetryableFailure,
            );

            throw $exception;
        }
    }

    private function build(
        StoredInvoice $stored,
        IssuerProfile $profile,
        string $attemptId,
    ): BuildInvoiceDocumentResult {
        try {
            $details = $this->issuance->fromPayload($stored->payload);
            $secrets = $this->secrets->forReference($profile->credentialReference);
            $number = $this->number($stored, $profile, $details);
            $this->assertUnitCodesAreAllowed($profile, $details);

            $invoice = $this->domain->fromArray(
                $stored->id,
                $stored->createdAt,
                [...$stored->payload, 'number' => $number],
            );

            $document = $this->fev19->map($invoice, $this->context($profile, $details, $secrets));
        } catch (SecretNotAvailable $exception) {
            return $this->fail($attemptId, ProcessingErrorCategory::Configuration, $exception, InvoiceStatus::RetryableFailure);
        } catch (AuthorizationNotActive|NumberOutsideAuthorizedRange $exception) {
            return $this->fail($attemptId, ProcessingErrorCategory::Configuration, $exception, InvoiceStatus::RetryableFailure);
        } catch (InvalidArgumentException $exception) {
            return $this->fail($attemptId, ProcessingErrorCategory::InputValidation, $exception, InvoiceStatus::PermanentFailure);
        }

        $unsignedXml = $this->xml->generate($document);
        $this->store($stored->id, $attemptId, EvidenceKind::UnsignedXml, $unsignedXml, 'application/xml');
        $this->processing->advance($attemptId, ProcessingStage::Validating);

        $schema = $this->validator->validateSchema($unsignedXml, DianDocumentType::Invoice);
        $this->storeJson($stored->id, $attemptId, EvidenceKind::XsdUnsignedResult, $schema->toArray());

        if (! $schema->valid) {
            return $this->failValidation($attemptId, 'XSD_UNSIGNED_INVALID', sprintf(
                'The generated document does not satisfy the official XSD (%d findings).',
                count($schema->errors),
            ));
        }

        // Schematron runs here for early feedback, but it cannot be the gate:
        // the official rules require ds:Signature, so an unsigned document
        // always trips FAC03. Every finding is preserved as evidence and the
        // blocking check happens once the document is signed. Nothing in this
        // stage may present a document with findings as valid.
        $rules = $this->validator->validateRules($unsignedXml);
        $this->storeJson($stored->id, $attemptId, EvidenceKind::SchematronResult, $rules->toArray());

        $this->invoices->recordDocumentIdentity($stored->id, $document->invoiceNumber, $document->cufe);
        $this->processing->succeed($attemptId);

        return BuildInvoiceDocumentResult::built(
            $attemptId,
            $document->invoiceNumber,
            $document->cufe,
            $this->blockingFindings($rules),
        );
    }

    /** @return list<string> rule codes reported as fatal, in order */
    private function blockingFindings(SchematronValidationResult $rules): array
    {
        $codes = [];

        foreach ($rules->messages as $message) {
            if ($message->severity === SchematronSeverity::Fatal) {
                $codes[] = $message->ruleCode ?? 'UNKNOWN';
            }
        }

        return $codes;
    }

    private function number(
        StoredInvoice $stored,
        IssuerProfile $profile,
        InvoiceIssuanceDetails $details,
    ): string {
        $moment = $profile->localise($details->issuedAt);
        $supplied = $stored->payload['number'] ?? null;

        if (! is_string($supplied) || trim($supplied) === '') {
            return $this->numbers
                ->reserve($stored->issuerId, $stored->id, $profile->numbering, $moment)
                ->value;
        }

        $ordinal = $profile->numbering->ordinalOf($supplied);

        if ($ordinal === null) {
            throw new InvalidArgumentException(sprintf(
                'Number "%s" does not belong to authorization "%s" with prefix "%s".',
                $supplied,
                $profile->numbering->reference,
                $profile->numbering->prefix,
            ));
        }

        return $this->numbers
            ->claim($stored->issuerId, $stored->id, $profile->numbering, $ordinal, $moment)
            ->value;
    }

    private function assertUnitCodesAreAllowed(IssuerProfile $profile, InvoiceIssuanceDetails $details): void
    {
        foreach ($details->lineUnitCodes as $index => $unitCode) {
            if (! $profile->allowsUnitCode($unitCode)) {
                throw new InvalidArgumentException(sprintf(
                    'Unit code "%s" on line %d is not enabled for this issuer.',
                    $unitCode,
                    $index + 1,
                ));
            }
        }
    }

    private function context(
        IssuerProfile $profile,
        InvoiceIssuanceDetails $details,
        IssuerSecrets $secrets,
    ): InvoiceGenerationContext {
        return new InvoiceGenerationContext(
            issuerReference: $profile->reference,
            environment: $profile->environment,
            control: $profile->control,
            softwareCredentials: $profile->software->withPin($secrets->softwarePin()),
            supplier: $profile->supplier,
            customer: $details->customer,
            customizationId: $profile->customizationId,
            invoiceTypeCode: $profile->invoiceTypeCode,
            issuedAt: $details->issuedAt,
            paymentMeansId: $details->paymentMeansId,
            paymentMeansCode: $details->paymentMeansCode,
            paymentDueDate: $details->paymentDueDate,
            lineUnitCodes: $details->lineUnitCodes,
            taxMappings: $profile->taxMappings,
            calculationPolicy: $profile->calculationPolicy,
            technicalKey: $secrets->technicalKey(),
        );
    }

    private function store(
        string $invoiceId,
        string $attemptId,
        EvidenceKind $kind,
        string $contents,
        string $mediaType,
    ): void {
        $this->processing->recordEvidence(
            $attemptId,
            $kind,
            $this->evidence->put($invoiceId, $attemptId, $kind, $contents, $mediaType),
        );
    }

    /** @param array<string, mixed> $result */
    private function storeJson(string $invoiceId, string $attemptId, EvidenceKind $kind, array $result): void
    {
        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('A validation result could not be encoded as evidence.', 0, $exception);
        }

        $this->store($invoiceId, $attemptId, $kind, $encoded, 'application/json');
    }

    private function fail(
        string $attemptId,
        ProcessingErrorCategory $category,
        Throwable $exception,
        InvoiceStatus $status,
    ): BuildInvoiceDocumentResult {
        $error = new ProcessingError($category, $this->codeFor($exception), $exception->getMessage());
        $this->processing->fail($attemptId, $error, $status);

        return BuildInvoiceDocumentResult::failed($attemptId, $error);
    }

    private function failValidation(
        string $attemptId,
        string $code,
        string $message,
    ): BuildInvoiceDocumentResult {
        $error = new ProcessingError(ProcessingErrorCategory::LocalValidation, $code, $message);
        $this->processing->fail($attemptId, $error, InvoiceStatus::PermanentFailure);

        return BuildInvoiceDocumentResult::failed($attemptId, $error);
    }

    private function codeFor(Throwable $exception): string
    {
        $parts = explode('\\', $exception::class);

        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', end($parts)) ?? 'ERROR');
    }
}

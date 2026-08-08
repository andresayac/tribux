<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Contracts;

use App\Application\Invoices\Processing\Data\EvidenceEntry;
use App\Application\Invoices\Processing\Data\ProcessingAttempt;
use App\Application\Invoices\Processing\Data\StatusChange;
use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\ProcessingError;
use App\Application\Invoices\Processing\ProcessingStage;
use App\Application\Invoices\Processing\StatusChangeSource;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Dian\DianEnvironment;

/**
 * Ownership, audit trail and evidence metadata of invoice processing.
 *
 * Implementations must guarantee that at most one attempt is open per invoice
 * and that every status change goes through the core transition table. See
 * ADR 0016.
 */
interface InvoiceProcessingRepository
{
    /**
     * Take ownership of a queued invoice: move it to `building` and open the
     * next numbered attempt in a single transaction.
     *
     * Returns null when the invoice does not exist, is not queued, or already
     * has an open attempt. A worker that receives null must stop, not retry.
     */
    public function claimForBuilding(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt;

    /**
     * Take ownership of a signed invoice to submit it. The status stays
     * `signed` until a response — or the lack of one — is known, so a crash
     * mid-flight never looks like a completed submission.
     */
    public function claimForSubmission(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt;

    /**
     * Take ownership of a submitted or unreconciled invoice to query DIAN.
     * Polling never changes the status by itself and never resends.
     */
    public function claimForPolling(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt;

    /** Record progress inside an open attempt without changing the invoice status. */
    public function advance(string $attemptId, ProcessingStage $stage): ProcessingAttempt;

    /**
     * Preserve what a DIAN call returned. Messages are stored as a queryable
     * projection; the raw XML must also be kept through recordEvidence().
     *
     * @param  list<array{code:?string,message:?string}>  $dianMessages
     */
    public function recordRemoteExchange(
        string $attemptId,
        string $operation,
        ?int $httpStatus = null,
        ?string $zipKey = null,
        array $dianMessages = [],
    ): ProcessingAttempt;

    /** Attach already-stored artefact metadata to the attempt. */
    public function recordEvidence(string $attemptId, EvidenceKind $kind, StoredEvidence $evidence): EvidenceEntry;

    /**
     * Close an open attempt successfully.
     *
     * A null target leaves the invoice status untouched, which is what an
     * inconclusive status query must do: it produces evidence, not a verdict.
     */
    public function succeed(
        string $attemptId,
        ?InvoiceStatus $to = null,
        StatusChangeSource $source = StatusChangeSource::Worker,
    ): ProcessingAttempt;

    /**
     * Close an open attempt with a structured failure.
     *
     * An ambiguous transport failure closes the attempt with
     * `InvoiceStatus::AwaitingReconciliation`: the outcome is unknown, so the
     * document must be queried, never resent.
     */
    public function fail(
        string $attemptId,
        ProcessingError $error,
        ?InvoiceStatus $to = null,
        StatusChangeSource $source = StatusChangeSource::Worker,
    ): ProcessingAttempt;

    /** Re-queue an invoice left in a retryable failure so a new attempt can open. */
    public function requeue(string $invoiceId, StatusChangeSource $source): void;

    /** @return list<ProcessingAttempt> ordered by attempt number */
    public function attempts(string $invoiceId): array;

    /** @return list<StatusChange> ordered by occurrence */
    public function history(string $invoiceId): array;

    /** @return list<EvidenceEntry> ordered by creation */
    public function evidence(string $invoiceId): array;
}

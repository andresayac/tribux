<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Processing\AttemptOutcome;
use App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository;
use App\Application\Invoices\Processing\Data\EvidenceEntry;
use App\Application\Invoices\Processing\Data\ProcessingAttempt;
use App\Application\Invoices\Processing\Data\StatusChange;
use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\AttemptNotOpen;
use App\Application\Invoices\Processing\ProcessingError;
use App\Application\Invoices\Processing\ProcessingErrorCategory;
use App\Application\Invoices\Processing\ProcessingStage;
use App\Application\Invoices\Processing\StatusChangeSource;
use App\Infrastructure\Persistence\Models\InvoiceEvidenceRecord;
use App\Infrastructure\Persistence\Models\InvoiceProcessingAttemptRecord;
use App\Infrastructure\Persistence\Models\InvoiceRecord;
use App\Infrastructure\Persistence\Models\InvoiceStatusHistoryRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Core\Invoice\InvoiceStatusTransition;
use Tribux\Dian\DianEnvironment;

final readonly class EloquentInvoiceProcessingRepository implements InvoiceProcessingRepository
{
    public function __construct(
        private Clock $clock,
        private IdGenerator $ids,
    ) {}

    public function claimForBuilding(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt
    {
        return $this->open(
            $invoiceId,
            $environment,
            [InvoiceStatus::Queued],
            ProcessingStage::Building,
            InvoiceStatus::Building,
        );
    }

    public function claimForSubmission(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt
    {
        return $this->open(
            $invoiceId,
            $environment,
            [InvoiceStatus::Signed],
            ProcessingStage::Submitting,
            null,
        );
    }

    public function claimForPolling(string $invoiceId, DianEnvironment $environment): ?ProcessingAttempt
    {
        return $this->open(
            $invoiceId,
            $environment,
            [InvoiceStatus::Submitted, InvoiceStatus::AwaitingReconciliation],
            ProcessingStage::Polling,
            null,
        );
    }

    /**
     * @param  non-empty-list<InvoiceStatus>  $claimableFrom
     * @param  InvoiceStatus|null  $moveTo  status applied while claiming, when
     *                                      taking ownership is itself a state
     *                                      change
     */
    private function open(
        string $invoiceId,
        DianEnvironment $environment,
        array $claimableFrom,
        ProcessingStage $stage,
        ?InvoiceStatus $moveTo,
    ): ?ProcessingAttempt {
        try {
            return DB::transaction(function () use ($invoiceId, $environment, $claimableFrom, $stage, $moveTo): ?ProcessingAttempt {
                $invoice = InvoiceRecord::query()->whereKey($invoiceId)->lockForUpdate()->first();
                if (! $invoice instanceof InvoiceRecord) {
                    return null;
                }

                if (! in_array($this->statusOf($invoice), $claimableFrom, true)) {
                    return null;
                }

                if ($this->hasOpenAttempt($invoiceId)) {
                    return null;
                }

                $now = $this->clock->now();
                $attemptId = $this->ids->generate();

                // The partial unique index rejects a concurrent second insert
                // even where the row lock above is a no-op.
                $attempt = InvoiceProcessingAttemptRecord::query()->create([
                    'id' => $attemptId,
                    'invoice_id' => $invoiceId,
                    'attempt_number' => $this->nextAttemptNumber($invoiceId),
                    'environment' => $environment,
                    'stage' => $stage,
                    'started_at' => $now,
                ]);

                if ($moveTo !== null) {
                    $this->applyStatus($invoice, $moveTo, StatusChangeSource::Worker, $attemptId, $now);
                }

                return $this->toAttempt($attempt);
            }, 3);
        } catch (QueryException $exception) {
            // A concurrent worker won the open-attempt index.
            if ($this->hasOpenAttempt($invoiceId)) {
                return null;
            }

            throw $exception;
        }
    }

    public function advance(string $attemptId, ProcessingStage $stage): ProcessingAttempt
    {
        return DB::transaction(function () use ($attemptId, $stage): ProcessingAttempt {
            $attempt = $this->lockOpenAttempt($attemptId);
            $attempt->setAttribute('stage', $stage);
            $attempt->save();

            return $this->toAttempt($attempt);
        }, 3);
    }

    public function recordRemoteExchange(
        string $attemptId,
        string $operation,
        ?int $httpStatus = null,
        ?string $zipKey = null,
        array $dianMessages = [],
    ): ProcessingAttempt {
        return DB::transaction(function () use ($attemptId, $operation, $httpStatus, $zipKey, $dianMessages): ProcessingAttempt {
            $attempt = $this->lockOpenAttempt($attemptId);
            $attempt->setAttribute('operation', $operation);
            $attempt->setAttribute('last_http_status', $httpStatus);
            $attempt->setAttribute('dian_messages', $dianMessages);

            // A ZipKey identifies a package DIAN already holds. Never clear one.
            if ($zipKey !== null) {
                $attempt->setAttribute('zip_key', $zipKey);
            }

            $attempt->save();

            return $this->toAttempt($attempt);
        }, 3);
    }

    public function recordEvidence(string $attemptId, EvidenceKind $kind, StoredEvidence $evidence): EvidenceEntry
    {
        return DB::transaction(function () use ($attemptId, $kind, $evidence): EvidenceEntry {
            $attempt = $this->lockOpenAttempt($attemptId);
            $now = $this->clock->now();

            $record = InvoiceEvidenceRecord::query()->create([
                'id' => $this->ids->generate(),
                'invoice_id' => (string) $attempt->getAttribute('invoice_id'),
                'attempt_id' => $attemptId,
                'kind' => $kind,
                'storage_reference' => $evidence->reference,
                'sha256' => $evidence->sha256,
                'bytes' => $evidence->bytes,
                'media_type' => $evidence->mediaType,
                'created_at' => $now,
            ]);

            return $this->toEvidence($record);
        }, 3);
    }

    public function succeed(
        string $attemptId,
        ?InvoiceStatus $to = null,
        StatusChangeSource $source = StatusChangeSource::Worker,
    ): ProcessingAttempt {
        return $this->close($attemptId, $to, $source, AttemptOutcome::Succeeded, null);
    }

    public function fail(
        string $attemptId,
        ProcessingError $error,
        ?InvoiceStatus $to = null,
        StatusChangeSource $source = StatusChangeSource::Worker,
    ): ProcessingAttempt {
        return $this->close($attemptId, $to, $source, AttemptOutcome::Failed, $error);
    }

    public function requeue(string $invoiceId, StatusChangeSource $source): void
    {
        DB::transaction(function () use ($invoiceId, $source): void {
            $invoice = InvoiceRecord::query()->whereKey($invoiceId)->lockForUpdate()->first();
            if (! $invoice instanceof InvoiceRecord) {
                throw new RuntimeException(sprintf('Invoice "%s" does not exist.', $invoiceId));
            }

            if ($this->hasOpenAttempt($invoiceId)) {
                throw new RuntimeException(sprintf('Invoice "%s" still has an open processing attempt.', $invoiceId));
            }

            $this->applyStatus($invoice, InvoiceStatus::Queued, $source, null, $this->clock->now());
        }, 3);
    }

    public function attempts(string $invoiceId): array
    {
        $records = InvoiceProcessingAttemptRecord::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('attempt_number')
            ->get();

        return array_map($this->toAttempt(...), array_values($records->all()));
    }

    public function history(string $invoiceId): array
    {
        $records = InvoiceStatusHistoryRecord::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return array_map($this->toStatusChange(...), array_values($records->all()));
    }

    public function evidence(string $invoiceId): array
    {
        $records = InvoiceEvidenceRecord::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return array_map($this->toEvidence(...), array_values($records->all()));
    }

    private function close(
        string $attemptId,
        ?InvoiceStatus $to,
        StatusChangeSource $source,
        AttemptOutcome $outcome,
        ?ProcessingError $error,
    ): ProcessingAttempt {
        return DB::transaction(function () use ($attemptId, $to, $source, $outcome, $error): ProcessingAttempt {
            $attempt = $this->lockOpenAttempt($attemptId);
            $invoiceId = (string) $attempt->getAttribute('invoice_id');

            $invoice = InvoiceRecord::query()->whereKey($invoiceId)->lockForUpdate()->first();
            if (! $invoice instanceof InvoiceRecord) {
                throw new RuntimeException(sprintf('Invoice "%s" does not exist.', $invoiceId));
            }

            $now = $this->clock->now();

            // Guard first: an illegal target must not close the attempt either.
            if ($to !== null) {
                $this->applyStatus($invoice, $to, $source, $attemptId, $now);
            }

            $attempt->setAttribute('outcome', $outcome);
            $attempt->setAttribute('finished_at', $now);
            $attempt->setAttribute('error_category', $error?->category);
            $attempt->setAttribute('error_code', $error?->code);
            $attempt->setAttribute('error_message', $error?->message);
            $attempt->save();

            return $this->toAttempt($attempt);
        }, 3);
    }

    private function applyStatus(
        InvoiceRecord $invoice,
        InvoiceStatus $to,
        StatusChangeSource $source,
        ?string $attemptId,
        DateTimeImmutable $occurredAt,
    ): void {
        $from = $this->statusOf($invoice);
        InvoiceStatusTransition::guard($from, $to);

        $invoice->setAttribute('status', $to);
        $invoice->save();

        InvoiceStatusHistoryRecord::query()->create([
            'id' => $this->ids->generate(),
            'invoice_id' => (string) $invoice->getKey(),
            'attempt_id' => $attemptId,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function lockOpenAttempt(string $attemptId): InvoiceProcessingAttemptRecord
    {
        $attempt = InvoiceProcessingAttemptRecord::query()
            ->whereKey($attemptId)
            ->whereNull('finished_at')
            ->lockForUpdate()
            ->first();

        if (! $attempt instanceof InvoiceProcessingAttemptRecord) {
            throw AttemptNotOpen::withId($attemptId);
        }

        return $attempt;
    }

    private function hasOpenAttempt(string $invoiceId): bool
    {
        return InvoiceProcessingAttemptRecord::query()
            ->where('invoice_id', $invoiceId)
            ->whereNull('finished_at')
            ->exists();
    }

    private function nextAttemptNumber(string $invoiceId): int
    {
        $highest = InvoiceProcessingAttemptRecord::query()
            ->where('invoice_id', $invoiceId)
            ->max('attempt_number');

        return is_numeric($highest) ? ((int) $highest) + 1 : 1;
    }

    private function statusOf(InvoiceRecord $invoice): InvoiceStatus
    {
        $status = $invoice->getAttribute('status');
        if (! $status instanceof InvoiceStatus) {
            throw new RuntimeException('Stored invoice contains an invalid status.');
        }

        return $status;
    }

    private function toAttempt(InvoiceProcessingAttemptRecord $record): ProcessingAttempt
    {
        $stage = $record->getAttribute('stage');
        $environment = $record->getAttribute('environment');
        $outcome = $record->getAttribute('outcome');
        $category = $record->getAttribute('error_category');

        if (! $stage instanceof ProcessingStage || ! $environment instanceof DianEnvironment) {
            throw new RuntimeException('Stored processing attempt contains invalid stage or environment data.');
        }

        $error = null;
        if ($category instanceof ProcessingErrorCategory) {
            $error = new ProcessingError(
                $category,
                (string) $record->getAttribute('error_code'),
                (string) $record->getAttribute('error_message'),
            );
        }

        return new ProcessingAttempt(
            id: (string) $record->getKey(),
            invoiceId: (string) $record->getAttribute('invoice_id'),
            attemptNumber: (int) $record->getAttribute('attempt_number'),
            environment: $environment,
            stage: $stage,
            outcome: $outcome instanceof AttemptOutcome ? $outcome : null,
            startedAt: $this->timestamp($record->getAttribute('started_at')),
            finishedAt: $this->nullableTimestamp($record->getAttribute('finished_at')),
            operation: $this->nullableString($record->getAttribute('operation')),
            zipKey: $this->nullableString($record->getAttribute('zip_key')),
            lastHttpStatus: $this->nullableInt($record->getAttribute('last_http_status')),
            error: $error,
            dianMessages: $this->dianMessages($record->getAttribute('dian_messages')),
        );
    }

    private function toStatusChange(InvoiceStatusHistoryRecord $record): StatusChange
    {
        $to = $record->getAttribute('to_status');
        $from = $record->getAttribute('from_status');
        $source = $record->getAttribute('source');

        if (! $to instanceof InvoiceStatus || ! $source instanceof StatusChangeSource) {
            throw new RuntimeException('Stored status change contains invalid status or source data.');
        }

        return new StatusChange(
            id: (string) $record->getKey(),
            invoiceId: (string) $record->getAttribute('invoice_id'),
            from: $from instanceof InvoiceStatus ? $from : null,
            to: $to,
            source: $source,
            occurredAt: $this->timestamp($record->getAttribute('occurred_at')),
            attemptId: $this->nullableString($record->getAttribute('attempt_id')),
        );
    }

    private function toEvidence(InvoiceEvidenceRecord $record): EvidenceEntry
    {
        $kind = $record->getAttribute('kind');
        if (! $kind instanceof EvidenceKind) {
            throw new RuntimeException('Stored evidence contains an invalid kind.');
        }

        return new EvidenceEntry(
            id: (string) $record->getKey(),
            invoiceId: (string) $record->getAttribute('invoice_id'),
            attemptId: $this->nullableString($record->getAttribute('attempt_id')),
            kind: $kind,
            stored: new StoredEvidence(
                (string) $record->getAttribute('storage_reference'),
                (string) $record->getAttribute('sha256'),
                (int) $record->getAttribute('bytes'),
                (string) $record->getAttribute('media_type'),
            ),
            createdAt: $this->timestamp($record->getAttribute('created_at')),
        );
    }

    /** @return list<array{code:?string,message:?string}> */
    private function dianMessages(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $message) {
            if (! is_array($message)) {
                continue;
            }

            $messages[] = [
                'code' => isset($message['code']) && is_scalar($message['code']) ? (string) $message['code'] : null,
                'message' => isset($message['message']) && is_scalar($message['message']) ? (string) $message['message'] : null,
            ];
        }

        return $messages;
    }

    private function timestamp(mixed $value): DateTimeImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            throw new RuntimeException('Stored processing row contains an invalid timestamp.');
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    private function nullableTimestamp(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->timestamp($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}

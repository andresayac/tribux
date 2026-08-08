<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Data\InvoiceCreationResult;
use App\Application\Invoices\Data\InvoiceView;
use App\Application\Invoices\Exceptions\IdempotencyConflict;
use App\Application\Invoices\Processing\StatusChangeSource;
use App\Infrastructure\Persistence\Models\IdempotencyKeyRecord;
use App\Infrastructure\Persistence\Models\InvoiceRecord;
use App\Infrastructure\Persistence\Models\InvoiceStatusHistoryRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceStatus;

final class EloquentInvoiceRepository implements InvoiceRepository
{
    private const string OPERATION = 'create_invoice';

    public function __construct(private readonly IdGenerator $ids) {}

    public function createIdempotently(
        Invoice $invoice,
        array $payload,
        string $idempotencyKey,
        string $requestHash,
    ): InvoiceCreationResult {
        try {
            return DB::transaction(function () use ($invoice, $payload, $idempotencyKey, $requestHash): InvoiceCreationResult {
                $existing = $this->findIdempotencyRecord($invoice->issuerId, $idempotencyKey, true);
                if ($existing !== null) {
                    return $this->replay($existing, $requestHash);
                }

                InvoiceRecord::query()->create([
                    'id' => (string) $invoice->id,
                    'issuer_id' => $invoice->issuerId,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'payload' => $payload,
                    'cufe' => null,
                    'created_at' => $invoice->createdAt,
                    'updated_at' => $invoice->createdAt,
                ]);

                // The audit trail starts at creation so the history is complete
                // even for an invoice no worker ever picks up.
                InvoiceStatusHistoryRecord::query()->create([
                    'id' => $this->ids->generate(),
                    'invoice_id' => (string) $invoice->id,
                    'attempt_id' => null,
                    'from_status' => null,
                    'to_status' => $invoice->status,
                    'source' => StatusChangeSource::Api,
                    'occurred_at' => $invoice->createdAt,
                ]);

                IdempotencyKeyRecord::query()->create([
                    'issuer_id' => $invoice->issuerId,
                    'operation' => self::OPERATION,
                    'key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'invoice_id' => (string) $invoice->id,
                    'expires_at' => $invoice->createdAt->modify('+24 hours'),
                ]);

                return new InvoiceCreationResult($this->toView($invoice), false);
            }, 3);
        } catch (QueryException $exception) {
            // A concurrent request may win the unique idempotency-key insert.
            $existing = $this->findIdempotencyRecord($invoice->issuerId, $idempotencyKey, false);
            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $requestHash);
        }
    }

    public function find(string $invoiceId): ?InvoiceView
    {
        $record = InvoiceRecord::query()->find($invoiceId);

        return $record instanceof InvoiceRecord ? $this->recordToView($record) : null;
    }

    private function findIdempotencyRecord(string $issuerId, string $key, bool $lock): ?IdempotencyKeyRecord
    {
        $query = IdempotencyKeyRecord::query()
            ->where('issuer_id', $issuerId)
            ->where('operation', self::OPERATION)
            ->where('key', $key);

        if ($lock) {
            $query->lockForUpdate();
        }

        $record = $query->first();

        return $record instanceof IdempotencyKeyRecord ? $record : null;
    }

    private function replay(IdempotencyKeyRecord $idempotency, string $requestHash): InvoiceCreationResult
    {
        if (! hash_equals((string) $idempotency->getAttribute('request_hash'), $requestHash)) {
            throw new IdempotencyConflict;
        }

        $invoice = InvoiceRecord::query()->find($idempotency->getAttribute('invoice_id'));
        if (! $invoice instanceof InvoiceRecord) {
            throw new RuntimeException('Idempotency record points to a missing invoice.');
        }

        return new InvoiceCreationResult($this->recordToView($invoice), true);
    }

    private function toView(Invoice $invoice): InvoiceView
    {
        return new InvoiceView(
            id: (string) $invoice->id,
            issuerId: $invoice->issuerId,
            status: $invoice->status,
            number: $invoice->number,
            cufe: null,
            createdAt: $invoice->createdAt,
        );
    }

    private function recordToView(InvoiceRecord $record): InvoiceView
    {
        $status = $record->getAttribute('status');
        $createdAt = $record->getAttribute('created_at');

        if (! $status instanceof InvoiceStatus || ! $createdAt instanceof DateTimeInterface) {
            throw new RuntimeException('Stored invoice contains invalid status or timestamp data.');
        }

        return new InvoiceView(
            id: (string) $record->getKey(),
            issuerId: (string) $record->getAttribute('issuer_id'),
            status: $status,
            number: $this->nullableString($record->getAttribute('number')),
            cufe: $this->nullableString($record->getAttribute('cufe')),
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

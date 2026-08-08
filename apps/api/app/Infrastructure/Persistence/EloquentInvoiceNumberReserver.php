<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Numbering\Contracts\InvoiceNumberReserver;
use App\Application\Invoices\Numbering\Exceptions\ReservationContention;
use App\Infrastructure\Persistence\Models\InvoiceNumberReservationRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tribux\Core\Numbering\NumberingAuthorization;
use Tribux\Core\Numbering\NumberOutsideAuthorizedRange;
use Tribux\Core\Numbering\ReservedNumber;

/**
 * Reserves by inserting into an append-only ledger guarded by unique indexes,
 * rather than by locking and bumping a counter.
 *
 * The unique index is the guarantee, so correctness does not depend on the
 * engine honouring `select ... for update`, and the same code is exercised by
 * the fast SQLite suite and by PostgreSQL. A loser of the race simply sees the
 * next free ordinal and tries again.
 */
final readonly class EloquentInvoiceNumberReserver implements InvoiceNumberReserver
{
    private const int MAX_ATTEMPTS = 25;

    public function __construct(
        private Clock $clock,
        private IdGenerator $ids,
    ) {}

    public function reserve(
        string $issuerId,
        string $invoiceId,
        NumberingAuthorization $authorization,
        DateTimeImmutable $issuerLocalMoment,
    ): ReservedNumber {
        $existing = $this->find($invoiceId);
        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $ordinal = $this->nextOrdinal($issuerId, $authorization);

            if (! $authorization->covers($ordinal)) {
                throw NumberOutsideAuthorizedRange::exhausted($authorization);
            }

            $authorization->assertCanIssue($ordinal, $issuerLocalMoment);
            $reserved = ReservedNumber::from($authorization, $ordinal);

            try {
                // A savepoint keeps an ambient transaction usable after
                // PostgreSQL aborts the losing insert.
                DB::transaction(function () use ($issuerId, $invoiceId, $reserved): void {
                    InvoiceNumberReservationRecord::query()->create([
                        'id' => $this->ids->generate(),
                        'issuer_id' => $issuerId,
                        'authorization_reference' => $reserved->authorizationReference,
                        'prefix' => $reserved->prefix,
                        'ordinal' => $reserved->ordinal,
                        'value' => $reserved->value,
                        'invoice_id' => $invoiceId,
                        'reserved_at' => $this->clock->now(),
                    ]);
                });

                return $reserved;
            } catch (QueryException) {
                // Either another invoice took this ordinal, or another worker
                // reserved for this same invoice. Only the second is final.
                $existing = $this->find($invoiceId);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        throw ReservationContention::after(sprintf('a number for invoice "%s"', $invoiceId), self::MAX_ATTEMPTS);
    }

    public function find(string $invoiceId): ?ReservedNumber
    {
        $record = InvoiceNumberReservationRecord::query()->where('invoice_id', $invoiceId)->first();

        if (! $record instanceof InvoiceNumberReservationRecord) {
            return null;
        }

        return new ReservedNumber(
            (string) $record->getAttribute('authorization_reference'),
            (string) $record->getAttribute('prefix'),
            (int) $record->getAttribute('ordinal'),
            (string) $record->getAttribute('value'),
        );
    }

    private function nextOrdinal(string $issuerId, NumberingAuthorization $authorization): int
    {
        $highest = InvoiceNumberReservationRecord::query()
            ->where('issuer_id', $issuerId)
            ->where('authorization_reference', $authorization->reference)
            ->where('prefix', $authorization->prefix)
            ->max('ordinal');

        return is_numeric($highest) ? ((int) $highest) + 1 : $authorization->from;
    }
}

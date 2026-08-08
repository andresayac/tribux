<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Numbering\Contracts\DocumentSequenceReserver;
use App\Application\Invoices\Numbering\DocumentSequenceScope;
use App\Application\Invoices\Numbering\Exceptions\ReservationContention;
use App\Infrastructure\Persistence\Models\DocumentSequenceReservationRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Same append-only ledger strategy as the invoice number reserver. */
final readonly class EloquentDocumentSequenceReserver implements DocumentSequenceReserver
{
    private const int MAX_ATTEMPTS = 25;

    public function __construct(
        private Clock $clock,
        private IdGenerator $ids,
    ) {}

    public function reserve(
        string $issuerId,
        DocumentSequenceScope $scope,
        int $calendarYear,
        string $ownerId,
    ): int {
        $existing = $this->find($issuerId, $scope, $ownerId);
        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $ordinal = $this->nextOrdinal($issuerId, $scope, $calendarYear);

            try {
                DB::transaction(function () use ($issuerId, $scope, $calendarYear, $ordinal, $ownerId): void {
                    DocumentSequenceReservationRecord::query()->create([
                        'id' => $this->ids->generate(),
                        'issuer_id' => $issuerId,
                        'scope' => $scope,
                        'calendar_year' => $calendarYear,
                        'ordinal' => $ordinal,
                        'owner_id' => $ownerId,
                        'reserved_at' => $this->clock->now(),
                    ]);
                });

                return $ordinal;
            } catch (QueryException) {
                $existing = $this->find($issuerId, $scope, $ownerId);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        throw ReservationContention::after(
            sprintf('a %s sequence for owner "%s"', $scope->value, $ownerId),
            self::MAX_ATTEMPTS,
        );
    }

    private function find(string $issuerId, DocumentSequenceScope $scope, string $ownerId): ?int
    {
        $record = DocumentSequenceReservationRecord::query()
            ->where('issuer_id', $issuerId)
            ->where('scope', $scope->value)
            ->where('owner_id', $ownerId)
            ->first();

        return $record instanceof DocumentSequenceReservationRecord
            ? (int) $record->getAttribute('ordinal')
            : null;
    }

    private function nextOrdinal(string $issuerId, DocumentSequenceScope $scope, int $calendarYear): int
    {
        $highest = DocumentSequenceReservationRecord::query()
            ->where('issuer_id', $issuerId)
            ->where('scope', $scope->value)
            ->where('calendar_year', $calendarYear)
            ->max('ordinal');

        return is_numeric($highest) ? ((int) $highest) + 1 : 1;
    }
}

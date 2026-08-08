<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

/**
 * Legal internal transitions of an invoice, as decided in ADR 0016.
 *
 * The table is deliberately explicit: no status may be written by the
 * application without passing through this guard, so a worker cannot skip
 * building, signing or submission stages.
 */
final class InvoiceStatusTransition
{
    /** @var array<string, list<InvoiceStatus>> */
    private const array TABLE = [
        'draft' => [InvoiceStatus::Queued],
        'queued' => [InvoiceStatus::Building, InvoiceStatus::PermanentFailure],
        'building' => [
            InvoiceStatus::Signed,
            InvoiceStatus::RetryableFailure,
            InvoiceStatus::PermanentFailure,
        ],
        'signed' => [
            InvoiceStatus::Submitted,
            InvoiceStatus::AwaitingReconciliation,
            InvoiceStatus::RetryableFailure,
        ],
        'submitted' => [
            InvoiceStatus::Accepted,
            InvoiceStatus::Rejected,
            InvoiceStatus::AwaitingReconciliation,
        ],
        'awaiting_reconciliation' => [
            InvoiceStatus::Submitted,
            InvoiceStatus::Accepted,
            InvoiceStatus::Rejected,
            InvoiceStatus::PermanentFailure,
        ],
        'retryable_failure' => [InvoiceStatus::Queued, InvoiceStatus::PermanentFailure],
        'accepted' => [],
        'rejected' => [],
        'permanent_failure' => [],
    ];

    private function __construct() {}

    /** @return list<InvoiceStatus> */
    public static function allowedFrom(InvoiceStatus $from): array
    {
        return self::TABLE[$from->value];
    }

    public static function isAllowed(InvoiceStatus $from, InvoiceStatus $to): bool
    {
        return in_array($to, self::TABLE[$from->value], true);
    }

    public static function isTerminal(InvoiceStatus $status): bool
    {
        return self::TABLE[$status->value] === [];
    }

    /**
     * A submission failure is only safe to retry when it happened before the
     * request bytes reached DIAN. Everything else is ambiguous and must be
     * reconciled by querying, never by resending. See ADR 0016.
     */
    public static function isRetryable(InvoiceStatus $status): bool
    {
        return $status === InvoiceStatus::RetryableFailure;
    }

    public static function guard(InvoiceStatus $from, InvoiceStatus $to): void
    {
        if (! self::isAllowed($from, $to)) {
            throw IllegalStatusTransition::between($from, $to);
        }
    }
}

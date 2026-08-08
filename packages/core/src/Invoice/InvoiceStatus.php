<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

/**
 * Internal Tribux lifecycle of an invoice.
 *
 * This enum never mirrors a DIAN status. A DIAN response is preserved as
 * evidence and projected separately; see ADR 0016.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Building = 'building';
    case Signed = 'signed';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case AwaitingReconciliation = 'awaiting_reconciliation';
    case RetryableFailure = 'retryable_failure';
    case PermanentFailure = 'permanent_failure';
}

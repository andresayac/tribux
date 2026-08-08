<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Building = 'building';
    case Signed = 'signed';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case RetryableFailure = 'retryable_failure';
    case PermanentFailure = 'permanent_failure';
}

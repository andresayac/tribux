<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

/**
 * Where a processing attempt currently is, or where it stopped.
 *
 * The stage is finer-grained than the invoice status on purpose: several
 * stages happen while the invoice is still `building`, and the operator needs
 * to know which one failed. See ADR 0016.
 */
enum ProcessingStage: string
{
    case Building = 'building';
    case Validating = 'validating';
    case Signing = 'signing';
    case Packaging = 'packaging';
    case Submitting = 'submitting';
    case Polling = 'polling';
}

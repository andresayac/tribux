<?php

declare(strict_types=1);

namespace App\Application\Invoices\Building;

enum BuildOutcome: string
{
    /** The document was generated and locally validated. */
    case Built = 'built';

    /**
     * Somebody else owns the invoice, or it is no longer queued. A worker that
     * sees this must stop, not retry: the work is already done or in progress.
     */
    case NotClaimable = 'not_claimable';

    /** The attempt was closed with a structured failure. */
    case Failed = 'failed';

    /**
     * The issuer is not configured, so no attempt was opened and the invoice
     * stays queued. Fixing the configuration and running again is enough; no
     * number and no attempt number were consumed.
     */
    case NotConfigured = 'not_configured';
}

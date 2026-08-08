<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Contracts;

use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\EvidenceNotAllowed;

/**
 * Writes and reads the bytes of an audit artefact. See ADR 0016.
 *
 * The database only ever holds what put() returns: a reference, a digest, a
 * size and a media type. Implementations must be replaceable by object storage
 * without the calling use case noticing.
 */
interface EvidenceStore
{
    /**
     * @throws EvidenceNotAllowed when policy disables this kind; check allows() first
     */
    public function put(
        string $invoiceId,
        string $attemptId,
        EvidenceKind $kind,
        string $contents,
        string $mediaType,
    ): StoredEvidence;

    /** Whether policy permits storing this kind at all. */
    public function allows(EvidenceKind $kind): bool;

    /** Reads an artefact back for reconciliation or a controlled export. */
    public function get(string $reference): string;
}

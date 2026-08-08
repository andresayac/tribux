<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Exceptions;

use App\Application\Invoices\Processing\EvidenceKind;
use RuntimeException;

/**
 * Storing this artefact is disabled by policy.
 *
 * It is thrown rather than silently skipped so a caller cannot believe it kept
 * evidence it never kept.
 */
final class EvidenceNotAllowed extends RuntimeException
{
    public static function forKind(EvidenceKind $kind): self
    {
        return new self(sprintf(
            'Storing "%s" evidence is disabled. Enable TRIBUX_EVIDENCE_STORE_SOAP_REQUESTS to keep it.',
            $kind->value,
        ));
    }
}

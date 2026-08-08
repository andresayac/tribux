<?php

declare(strict_types=1);

namespace App\Application\Invoices\Numbering\Contracts;

use App\Application\Invoices\Numbering\DocumentSequenceScope;

interface DocumentSequenceReserver
{
    /**
     * Take the next annual ordinal of a file-name sequence for one owner.
     *
     * Returns an ordinal, never an encoded token: how an ordinal becomes the
     * eight-character token is an issuer decision while Q-008 is open.
     *
     * Idempotent per owner, so re-running a stage cannot consume a second
     * ordinal and silently rename an artefact.
     *
     * The owner is a Tribux UUID — an invoice or a processing attempt — and the
     * storage column is typed as such.
     */
    public function reserve(
        string $issuerId,
        DocumentSequenceScope $scope,
        int $calendarYear,
        string $ownerId,
    ): int;
}

<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Data;

use App\Application\Invoices\Processing\EvidenceKind;
use DateTimeImmutable;

final readonly class EvidenceEntry
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public ?string $attemptId,
        public EvidenceKind $kind,
        public StoredEvidence $stored,
        public DateTimeImmutable $createdAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Evidence;

use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\EvidenceNotAllowed;
use InvalidArgumentException;
use RuntimeException;

/** Test double with the same reference layout and policy as the disk store. */
final class InMemoryEvidenceStore implements EvidenceStore
{
    /** @var array<string, string> */
    private array $artefacts = [];

    public function __construct(private readonly bool $storeSoapRequests = false) {}

    public function allows(EvidenceKind $kind): bool
    {
        return ! $kind->requiresExplicitOptIn() || $this->storeSoapRequests;
    }

    public function put(
        string $invoiceId,
        string $attemptId,
        EvidenceKind $kind,
        string $contents,
        string $mediaType,
    ): StoredEvidence {
        if (! $this->allows($kind)) {
            throw EvidenceNotAllowed::forKind($kind);
        }

        if ($contents === '') {
            throw new InvalidArgumentException('Evidence contents cannot be empty.');
        }

        $digest = hash('sha256', $contents);
        $reference = EvidenceReference::build($invoiceId, $attemptId, $kind, $digest, $mediaType);
        $this->artefacts[$reference] = $contents;

        return new StoredEvidence($reference, $digest, strlen($contents), $mediaType);
    }

    public function get(string $reference): string
    {
        return $this->artefacts[$reference]
            ?? throw new RuntimeException(sprintf('Evidence "%s" is missing from the evidence store.', $reference));
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Evidence;

use App\Application\Invoices\Processing\Contracts\EvidenceStore;
use App\Application\Invoices\Processing\Data\StoredEvidence;
use App\Application\Invoices\Processing\EvidenceKind;
use App\Application\Invoices\Processing\Exceptions\EvidenceNotAllowed;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

/**
 * Writes evidence to a Laravel disk.
 *
 * The disk is configuration, so the same adapter serves a local directory in
 * development and S3-compatible object storage in production. A local disk is
 * never an acceptable final home for a legal document.
 */
final readonly class DiskEvidenceStore implements EvidenceStore
{
    public function __construct(
        private Filesystem $disk,
        private bool $storeSoapRequests = false,
    ) {}

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

        if ($this->disk->put($reference, $contents) === false) {
            throw new RuntimeException(sprintf('Evidence "%s" could not be written.', $reference));
        }

        return new StoredEvidence($reference, $digest, strlen($contents), $mediaType);
    }

    public function get(string $reference): string
    {
        $contents = $this->disk->get($reference);

        if (! is_string($contents)) {
            throw new RuntimeException(sprintf('Evidence "%s" is missing from the evidence store.', $reference));
        }

        return $contents;
    }
}

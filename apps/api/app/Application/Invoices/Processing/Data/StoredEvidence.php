<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Data;

use InvalidArgumentException;

/**
 * What an evidence store returns after persisting an artefact: enough to find
 * it again and to prove it was not altered. The bytes themselves stay outside
 * the database.
 */
final readonly class StoredEvidence
{
    public function __construct(
        public string $reference,
        public string $sha256,
        public int $bytes,
        public string $mediaType,
    ) {
        if (trim($reference) === '' || strlen($reference) > 500) {
            throw new InvalidArgumentException('Evidence reference must be non-empty and at most 500 characters.');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $sha256) !== 1) {
            throw new InvalidArgumentException('Evidence digest must be a lowercase hexadecimal SHA-256.');
        }

        if ($bytes < 0) {
            throw new InvalidArgumentException('Evidence size cannot be negative.');
        }

        if (trim($mediaType) === '' || strlen($mediaType) > 150) {
            throw new InvalidArgumentException('Evidence media type must be non-empty and at most 150 characters.');
        }
    }

    public static function forContents(string $reference, string $contents, string $mediaType): self
    {
        return new self($reference, hash('sha256', $contents), strlen($contents), $mediaType);
    }
}

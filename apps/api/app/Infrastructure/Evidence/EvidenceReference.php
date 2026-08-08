<?php

declare(strict_types=1);

namespace App\Infrastructure\Evidence;

use App\Application\Invoices\Processing\EvidenceKind;
use InvalidArgumentException;

/**
 * Builds the storage key of an artefact.
 *
 * The key contains the content digest instead of a timestamp or a random
 * suffix, so storing the same bytes twice is idempotent and a reference is
 * reproducible from the artefact alone. Identifiers are validated as single
 * path segments before they become directory names.
 */
final readonly class EvidenceReference
{
    private const array EXTENSIONS = [
        'application/xml' => '.xml',
        'text/xml' => '.xml',
        'application/zip' => '.zip',
        'application/json' => '.json',
        'text/plain' => '.txt',
    ];

    public static function build(
        string $invoiceId,
        string $attemptId,
        EvidenceKind $kind,
        string $digest,
        string $mediaType,
    ): string {
        return sprintf(
            '%s/%s/%s-%s%s',
            self::segment($invoiceId, 'invoiceId'),
            self::segment($attemptId, 'attemptId'),
            $kind->value,
            $digest,
            self::EXTENSIONS[strtolower($mediaType)] ?? '.bin',
        );
    }

    private static function segment(string $value, string $field): string
    {
        if (preg_match('/\A[A-Za-z0-9._-]+\z/D', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException(sprintf('%s must be a single safe path segment.', $field));
        }

        return $value;
    }
}

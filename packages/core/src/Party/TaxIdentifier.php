<?php

declare(strict_types=1);

namespace Tribux\Core\Party;

use InvalidArgumentException;

/**
 * Framework- and catalog-agnostic party identifier.
 *
 * DIAN catalog validation belongs to the versioned DIAN adapter.
 */
final readonly class TaxIdentifier
{
    public function __construct(
        public string $value,
        public ?string $scheme = null,
    ) {
        if (trim($value) === '' || strlen($value) > 100) {
            throw new InvalidArgumentException('Tax identifier must be non-empty and at most 100 characters.');
        }

        if ($scheme !== null && (trim($scheme) === '' || strlen($scheme) > 50)) {
            throw new InvalidArgumentException('Tax identifier scheme must be null or a non-empty value of at most 50 characters.');
        }
    }
}

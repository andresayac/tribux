<?php

declare(strict_types=1);

namespace Tribux\Core\Tax;

use InvalidArgumentException;

/** Generic tax concept; DIAN code mappings live in packages/dian. */
final readonly class Tax
{
    public function __construct(
        public string $type,
        public TaxRate $rate,
    ) {
        if (trim($type) === '' || strlen($type) > 50) {
            throw new InvalidArgumentException('Tax type must be non-empty and at most 50 characters.');
        }
    }
}

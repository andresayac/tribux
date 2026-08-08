<?php

declare(strict_types=1);

namespace Tribux\Core\Party;

use InvalidArgumentException;

final readonly class Party
{
    public function __construct(
        public TaxIdentifier $taxIdentifier,
        public string $name,
        public ?string $email = null,
        public ?Address $address = null,
    ) {
        if (trim($name) === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Party name must be non-empty and at most 255 characters.');
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Party email must be a valid email address.');
        }
    }
}

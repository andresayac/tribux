<?php

declare(strict_types=1);

namespace Tribux\Core\Party;

use InvalidArgumentException;

final readonly class Address
{
    public function __construct(
        public string $line,
        public string $city,
        public string $countryCode,
        public ?string $postalCode = null,
    ) {
        if (trim($line) === '' || trim($city) === '') {
            throw new InvalidArgumentException('Address line and city must be non-empty.');
        }

        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException('Country code must contain two uppercase letters.');
        }
    }
}

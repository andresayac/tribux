<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceAddress
{
    public function __construct(
        public string $municipalityCode,
        public string $cityName,
        public string $departmentName,
        public string $departmentCode,
        public string $line,
        public string $countryCode = 'CO',
        public string $countryName = 'Colombia',
        public ?string $postalZone = null,
    ) {
        Fev19Value::text($municipalityCode, 'address.municipalityCode');
        Fev19Value::text($cityName, 'address.cityName');
        Fev19Value::text($departmentName, 'address.departmentName');
        Fev19Value::text($departmentCode, 'address.departmentCode');
        Fev19Value::text($line, 'address.line');
        Fev19Value::text($countryName, 'address.countryName');

        if (preg_match('/\A[A-Z]{2}\z/', $countryCode) !== 1) {
            throw new InvalidArgumentException('address.countryCode must be a two-letter uppercase code.');
        }

        if ($postalZone !== null) {
            Fev19Value::text($postalZone, 'address.postalZone');
        }
    }
}

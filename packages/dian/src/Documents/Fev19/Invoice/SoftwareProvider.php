<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class SoftwareProvider
{
    public function __construct(
        public string $taxId,
        public string $verificationDigit,
        public string $identificationSchemeName,
        public string $softwareId,
        public string $securityCode,
    ) {
        Fev19Value::text($taxId, 'softwareProvider.taxId');
        Fev19Value::digits($verificationDigit, 'softwareProvider.verificationDigit');
        Fev19Value::code($identificationSchemeName, 'softwareProvider.identificationSchemeName');
        Fev19Value::text($softwareId, 'softwareProvider.softwareId');
        Fev19Value::sha384($securityCode, 'softwareProvider.securityCode');
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use SensitiveParameter;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;
use Tribux\Dian\Software\SoftwareSecurityCodeCalculator;

/** Holds the software PIN in memory without exposing it as a public property. */
final readonly class InvoiceSoftwareCredentials
{
    public function __construct(
        public string $taxId,
        public string $verificationDigit,
        public string $identificationSchemeName,
        public string $softwareId,
        #[SensitiveParameter]
        private string $pin,
    ) {
        Fev19Value::text($taxId, 'softwareCredentials.taxId');
        Fev19Value::digits($verificationDigit, 'softwareCredentials.verificationDigit');
        Fev19Value::code($identificationSchemeName, 'softwareCredentials.identificationSchemeName');
        Fev19Value::text($softwareId, 'softwareCredentials.softwareId');
        Fev19Value::text($pin, 'softwareCredentials.pin');
    }

    public function providerFor(
        string $documentNumber,
        SoftwareSecurityCodeCalculator $calculator,
    ): SoftwareProvider {
        return new SoftwareProvider(
            taxId: $this->taxId,
            verificationDigit: $this->verificationDigit,
            identificationSchemeName: $this->identificationSchemeName,
            softwareId: $this->softwareId,
            securityCode: $calculator->calculate($this->softwareId, $this->pin, $documentNumber),
        );
    }
}

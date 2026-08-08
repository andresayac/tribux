<?php

declare(strict_types=1);

namespace App\Application\Issuers;

use SensitiveParameter;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceSoftwareCredentials;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

/**
 * The non-secret half of the DIAN software registration.
 *
 * The PIN is deliberately absent: it comes from a secret provider and is only
 * combined with this identity for as long as a security code has to be
 * computed.
 */
final readonly class SoftwareIdentity
{
    public function __construct(
        public string $taxId,
        public string $verificationDigit,
        public string $identificationSchemeName,
        public string $softwareId,
    ) {
        Fev19Value::text($taxId, 'software.taxId');
        Fev19Value::digits($verificationDigit, 'software.verificationDigit');
        Fev19Value::code($identificationSchemeName, 'software.identificationSchemeName');
        Fev19Value::text($softwareId, 'software.softwareId');
    }

    public function withPin(#[SensitiveParameter] string $pin): InvoiceSoftwareCredentials
    {
        return new InvoiceSoftwareCredentials(
            taxId: $this->taxId,
            verificationDigit: $this->verificationDigit,
            identificationSchemeName: $this->identificationSchemeName,
            softwareId: $this->softwareId,
            pin: $pin,
        );
    }
}

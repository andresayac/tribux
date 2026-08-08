<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceParty
{
    public function __construct(
        public string $accountTypeCode,
        public string $name,
        public string $identification,
        public string $verificationDigit,
        public string $identificationSchemeName,
        public string $taxLevelCode,
        public string $taxLevelListName,
        public string $taxSchemeId,
        public string $taxSchemeName,
        public InvoiceAddress $address,
        public ?string $registrationPrefix = null,
        public ?string $telephone = null,
        public ?string $email = null,
    ) {
        Fev19Value::code($accountTypeCode, 'party.accountTypeCode');
        Fev19Value::text($name, 'party.name');
        Fev19Value::text($identification, 'party.identification');
        Fev19Value::digits($verificationDigit, 'party.verificationDigit');
        Fev19Value::code($identificationSchemeName, 'party.identificationSchemeName');
        Fev19Value::text($taxLevelCode, 'party.taxLevelCode');
        Fev19Value::code($taxLevelListName, 'party.taxLevelListName');
        Fev19Value::code($taxSchemeId, 'party.taxSchemeId');
        Fev19Value::text($taxSchemeName, 'party.taxSchemeName');

        foreach (['registrationPrefix' => $registrationPrefix, 'telephone' => $telephone, 'email' => $email] as $field => $value) {
            if ($value !== null) {
                Fev19Value::text($value, 'party.'.$field);
            }
        }
    }
}

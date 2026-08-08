<?php

declare(strict_types=1);

namespace App\Application\Invoices\Building\Contracts;

use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Validation\Schematron\SchematronValidationResult;
use Tribux\Dian\Validation\XmlValidationResult;

/**
 * Local validation of a generated document against the official artefacts.
 *
 * A port because the real implementation needs the DIAN toolbox and a Java
 * runtime on disk, which a fast unit test must not require. Both methods return
 * the library results untouched: passing them is never enough to claim
 * compliance, and their findings must be preserved rather than reduced to a
 * boolean.
 */
interface Fev19DocumentValidator
{
    public function validateSchema(string $xml, DianDocumentType $type): XmlValidationResult;

    public function validateRules(string $xml): SchematronValidationResult;
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Validation\Schematron\SchematronMessage;
use Tribux\Dian\Validation\Schematron\SchematronSeverity;
use Tribux\Dian\Validation\Schematron\SchematronValidationResult;
use Tribux\Dian\Validation\XmlValidationError;
use Tribux\Dian\Validation\XmlValidationResult;

/**
 * Lets a test choose the verdicts without the DIAN toolbox or a Java runtime.
 *
 * It records the XML it received so a test can prove the pipeline validates the
 * document it actually generated.
 */
final class FakeFev19DocumentValidator implements Fev19DocumentValidator
{
    public ?string $schemaInput = null;

    public ?string $rulesInput = null;

    public function __construct(
        private XmlValidationResult $schema = new XmlValidationResult(true, []),
        private SchematronValidationResult $rules = new SchematronValidationResult(true, []),
    ) {}

    public static function rejectingSchema(): self
    {
        return new self(new XmlValidationResult(false, [
            new XmlValidationError(2, 1845, 12, 4, "Element 'cbc:ID': missing.", 'invoice.xml'),
        ]));
    }

    public static function reportingFad03(): self
    {
        return new self(
            new XmlValidationResult(true, []),
            new SchematronValidationResult(false, [
                new SchematronMessage(
                    SchematronSeverity::Fatal,
                    'FAD03',
                    'Regla: FAD03, Rechazo: ProfileID no corresponde.',
                    'Fatal: Regla: FAD03, Rechazo: ProfileID no corresponde.',
                ),
            ]),
        );
    }

    public function validateSchema(string $xml, DianDocumentType $type): XmlValidationResult
    {
        $this->schemaInput = $xml;

        return $this->schema;
    }

    public function validateRules(string $xml): SchematronValidationResult
    {
        $this->rulesInput = $xml;

        return $this->rules;
    }
}

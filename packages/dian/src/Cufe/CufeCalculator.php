<?php

declare(strict_types=1);

namespace Tribux\Dian\Cufe;

/**
 * FEV 1.9 CUFE-SHA384 calculator.
 *
 * Source: Anexo Tecnico FEV 1.9, sections 11.1 and 11.2, pages 654-659.
 */
final class CufeCalculator
{
    public function calculate(CufeInput $input): string
    {
        return hash('sha384', $this->canonicalString($input));
    }

    public function canonicalString(CufeInput $input): string
    {
        return $input->invoiceNumber
            .$input->issueDate
            .$input->issueTime
            .$input->lineExtensionAmount
            .'01'
            .$input->vatAmount
            .'04'
            .$input->incAmount
            .'03'
            .$input->icaAmount
            .$input->payableAmount
            .$input->issuerTaxId
            .$input->buyerIdentification
            .$input->technicalKey
            .$input->environment->profileExecutionId();
    }
}

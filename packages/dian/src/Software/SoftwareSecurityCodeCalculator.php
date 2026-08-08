<?php

declare(strict_types=1);

namespace Tribux\Dian\Software;

use InvalidArgumentException;

/**
 * Source: Anexo Tecnico FEV 1.9, section 11.8.
 */
final class SoftwareSecurityCodeCalculator
{
    public function calculate(string $softwareId, string $pin, string $documentNumber): string
    {
        foreach (compact('softwareId', 'pin', 'documentNumber') as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field));
            }
        }

        return hash('sha384', $softwareId.$pin.$documentNumber);
    }
}

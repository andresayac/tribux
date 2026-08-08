<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

final class SchematronMessageParser
{
    /** @return list<SchematronMessage> */
    public function parse(string $standardError): array
    {
        $messages = [];

        foreach (preg_split('/\R/u', $standardError) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/\A(Fatal|Warning):\s*(?:\[([A-Za-z0-9]+)\])?-?\s*(.*)\z/u', $line, $matches) === 1) {
                $messages[] = new SchematronMessage(
                    severity: $matches[1] === 'Fatal' ? SchematronSeverity::Fatal : SchematronSeverity::Warning,
                    ruleCode: $matches[2] !== '' ? $matches[2] : null,
                    message: trim($matches[3]),
                    original: $line,
                );
                continue;
            }

            $messages[] = new SchematronMessage(
                severity: SchematronSeverity::Diagnostic,
                ruleCode: null,
                message: $line,
                original: $line,
            );
        }

        return $messages;
    }
}

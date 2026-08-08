<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

final readonly class SchematronMessage
{
    public function __construct(
        public SchematronSeverity $severity,
        public ?string $ruleCode,
        public string $message,
        public string $original,
    ) {
    }

    /** @return array{severity: string, rule_code: string|null, message: string, original: string} */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'rule_code' => $this->ruleCode,
            'message' => $this->message,
            'original' => $this->original,
        ];
    }
}

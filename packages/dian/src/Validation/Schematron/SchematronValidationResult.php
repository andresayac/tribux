<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

final readonly class SchematronValidationResult
{
    /** @param list<SchematronMessage> $messages */
    public function __construct(
        public bool $valid,
        public array $messages,
    ) {
    }

    /** @return array{valid: bool, messages: list<array{severity: string, rule_code: string|null, message: string, original: string}>} */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'messages' => array_map(
                static fn (SchematronMessage $message): array => $message->toArray(),
                $this->messages,
            ),
        ];
    }
}

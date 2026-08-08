<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission;

/**
 * Normalized DIAN result. The raw response should be stored separately through
 * the audit/evidence layer when policy requires it.
 */
final readonly class SubmissionResult
{
    /** @param list<array{code:string,message:string}> $messages */
    public function __construct(
        public bool $accepted,
        public ?string $externalReference,
        public array $messages = [],
    ) {
    }
}

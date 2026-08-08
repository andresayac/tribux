<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission;

/**
 * Normalized DIAN result. The raw response should be stored separately through
 * the audit/evidence layer when policy requires it.
 *
 * @deprecated Flattening a DIAN answer to a boolean discards the codes,
 * messages and raw XML that reconciliation depends on. Use
 * SendTestSetAsyncResponse, GetStatusResponse or GetStatusZipResponse. See
 * ADR 0016.
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

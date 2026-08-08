<?php

declare(strict_types=1);

namespace App\Application\Invoices\Building;

use App\Application\Invoices\Processing\ProcessingError;

final readonly class BuildInvoiceDocumentResult
{
    /**
     * @param  list<string>  $unsignedRuleFindings  Schematron rule codes reported
     *                                              as fatal against the unsigned
     *                                              document. Informational here:
     *                                              the official rules require a
     *                                              signature, so the blocking
     *                                              check belongs to the signing
     *                                              stage. A non-empty list still
     *                                              means the document is not
     *                                              fit to submit.
     */
    private function __construct(
        public BuildOutcome $outcome,
        public ?string $attemptId = null,
        public ?string $number = null,
        public ?string $cufe = null,
        public ?ProcessingError $error = null,
        public array $unsignedRuleFindings = [],
    ) {}

    /** @param list<string> $unsignedRuleFindings */
    public static function built(
        string $attemptId,
        string $number,
        string $cufe,
        array $unsignedRuleFindings = [],
    ): self {
        return new self(
            BuildOutcome::Built,
            $attemptId,
            $number,
            $cufe,
            unsignedRuleFindings: $unsignedRuleFindings,
        );
    }

    public static function notClaimable(): self
    {
        return new self(BuildOutcome::NotClaimable);
    }

    public static function failed(string $attemptId, ProcessingError $error): self
    {
        return new self(BuildOutcome::Failed, $attemptId, error: $error);
    }

    public static function notConfigured(ProcessingError $error): self
    {
        return new self(BuildOutcome::NotConfigured, error: $error);
    }

    /** Whether the unsigned document is already known not to satisfy the rules. */
    public function hasUnsignedRuleFindings(): bool
    {
        return $this->unsignedRuleFindings !== [];
    }
}

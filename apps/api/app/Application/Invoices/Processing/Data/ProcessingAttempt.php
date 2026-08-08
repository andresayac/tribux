<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Data;

use App\Application\Invoices\Processing\AttemptOutcome;
use App\Application\Invoices\Processing\ProcessingError;
use App\Application\Invoices\Processing\ProcessingStage;
use DateTimeImmutable;
use Tribux\Dian\DianEnvironment;

final readonly class ProcessingAttempt
{
    /** @param list<array{code:?string,message:?string}> $dianMessages */
    public function __construct(
        public string $id,
        public string $invoiceId,
        public int $attemptNumber,
        public DianEnvironment $environment,
        public ProcessingStage $stage,
        public ?AttemptOutcome $outcome,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
        public ?string $operation = null,
        public ?string $zipKey = null,
        public ?int $lastHttpStatus = null,
        public ?ProcessingError $error = null,
        public array $dianMessages = [],
    ) {}

    public function isOpen(): bool
    {
        return $this->finishedAt === null;
    }
}

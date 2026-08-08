<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

final readonly class Fev19SubmissionPackage
{
    /** @param list<string> $documentFileNames */
    public function __construct(
        public string $fileName,
        public string $contents,
        public array $documentFileNames,
        public Fev19SubmissionMode $mode,
    ) {
    }
}

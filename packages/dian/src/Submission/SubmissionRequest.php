<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission;

use InvalidArgumentException;

final readonly class SubmissionRequest
{
    public function __construct(
        public string $documentId,
        public string $signedXml,
    ) {
        if ($documentId === '') {
            throw new InvalidArgumentException('documentId cannot be empty.');
        }

        if (trim($signedXml) === '') {
            throw new InvalidArgumentException('signedXml cannot be empty.');
        }
    }
}

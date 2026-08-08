<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission;

use InvalidArgumentException;

/**
 * @deprecated Companion of the deprecated DianGateway. A real submission needs
 * the ZIP package, the environment and the software credentials, not just a
 * signed XML string. See ADR 0016.
 */
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

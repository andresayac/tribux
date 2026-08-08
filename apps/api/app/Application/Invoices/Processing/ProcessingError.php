<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

use InvalidArgumentException;

/**
 * A structured, operator-safe reason why an attempt stopped.
 *
 * The message is persisted and may reach an operator, so callers must not put
 * secrets, certificate passwords, PINs or full payloads in it. The original
 * artefact belongs in the evidence store, not here.
 */
final readonly class ProcessingError
{
    public function __construct(
        public ProcessingErrorCategory $category,
        public string $code,
        public string $message,
    ) {
        if (trim($code) === '' || strlen($code) > 100) {
            throw new InvalidArgumentException('Processing error code must be non-empty and at most 100 characters.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('Processing error message must be non-empty.');
        }
    }

    public function isAutomaticallyRetryable(): bool
    {
        return $this->category->isAutomaticallyRetryable();
    }
}

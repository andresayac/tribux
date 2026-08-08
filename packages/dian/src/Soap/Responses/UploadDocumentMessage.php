<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class UploadDocumentMessage
{
    public function __construct(
        public ?string $documentKey,
        public ?string $processedMessage,
        public ?string $senderCode,
        public ?bool $success,
        public ?string $xmlFileName,
    ) {
    }
}

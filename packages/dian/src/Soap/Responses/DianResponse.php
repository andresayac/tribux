<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class DianResponse
{
    /** @param list<?string> $errorMessages */
    public function __construct(
        public array $errorMessages,
        public ?bool $isValid,
        public ?string $statusCode,
        public ?string $statusDescription,
        public ?string $statusMessage,
        public ?DianBase64Value $xmlBase64Bytes,
        public ?DianBase64Value $xmlBytes,
        public ?string $xmlDocumentKey,
        public ?string $xmlFileName,
    ) {
    }
}

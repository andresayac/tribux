<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class SendTestSetAsyncResponse
{
    /** @param list<UploadDocumentMessage> $messages */
    public function __construct(
        public int $httpStatusCode,
        public string $rawXml,
        public ?string $zipKey,
        public array $messages,
        public ?DianSoapFault $fault,
    ) {
    }

    public function isFault(): bool
    {
        return $this->fault !== null;
    }
}

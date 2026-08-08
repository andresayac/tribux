<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class GetStatusResponse
{
    public function __construct(
        public int $httpStatusCode,
        public string $rawXml,
        public ?DianResponse $result,
        public ?DianSoapFault $fault,
    ) {
    }

    public function isFault(): bool
    {
        return $this->fault !== null;
    }
}

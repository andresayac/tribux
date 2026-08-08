<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class GetStatusZipResponse
{
    /** @param ?list<?DianResponse> $results */
    public function __construct(
        public int $httpStatusCode,
        public string $rawXml,
        public ?array $results,
        public ?DianSoapFault $fault,
    ) {
    }

    public function isFault(): bool
    {
        return $this->fault !== null;
    }
}

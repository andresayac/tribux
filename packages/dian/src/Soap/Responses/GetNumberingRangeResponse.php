<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

/**
 * **The raw XML of this response contains the resolution technical key**,
 * because the WSDL puts TechnicalKey inside every NumberRangeResponse.
 *
 * It is preserved so a numbering answer can be reconciled, but it must never be
 * logged or written to the evidence store. There is deliberately no evidence
 * kind for a numbering query.
 */
final readonly class GetNumberingRangeResponse
{
    public function __construct(
        public int $httpStatusCode,
        public string $rawXml,
        public ?NumberRangeResponseList $result,
        public ?DianSoapFault $fault,
    ) {}

    public function isFault(): bool
    {
        return $this->fault !== null;
    }
}

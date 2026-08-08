<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

/**
 * GetNumberingRangeResult as the WSDL declares it: an operation code, a
 * description and a nillable list whose members are themselves nillable.
 */
final readonly class NumberRangeResponseList
{
    /** @param ?list<?NumberRangeResponse> $ranges */
    public function __construct(
        public ?string $operationCode,
        public ?string $operationDescription,
        public ?array $ranges,
    ) {}
}

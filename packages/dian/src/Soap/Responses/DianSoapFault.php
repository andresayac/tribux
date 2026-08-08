<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class DianSoapFault
{
    /** @param non-empty-list<array{language:?string,text:string}> $reasons */
    public function __construct(
        public string $code,
        public ?string $subcode,
        public array $reasons,
        public ?string $detailXml,
    ) {
    }
}

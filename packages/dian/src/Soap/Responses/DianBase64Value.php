<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

final readonly class DianBase64Value
{
    public function __construct(
        public string $encoded,
        public string $decoded,
    ) {
    }
}

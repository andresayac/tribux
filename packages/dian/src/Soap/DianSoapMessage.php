<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

final readonly class DianSoapMessage
{
    public function __construct(
        public DianSoapOperation $operation,
        public string $xml,
    ) {
    }

    public function contentType(): string
    {
        return sprintf(
            'application/soap+xml; charset=utf-8; action="%s"',
            $this->operation->action(),
        );
    }
}

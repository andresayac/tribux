<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

interface SoapMessageIdGenerator
{
    public function generate(): string;
}

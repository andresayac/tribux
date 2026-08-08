<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

interface SignatureIdGenerator
{
    public function generate(): string;
}

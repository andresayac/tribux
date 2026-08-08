<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DOMElement;

interface DianSoapBody
{
    public function operation(): DianSoapOperation;

    public function appendTo(DOMElement $soapBody): void;
}

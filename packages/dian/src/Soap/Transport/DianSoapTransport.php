<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Transport;

use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapMessage;

interface DianSoapTransport
{
    public function send(DianEndpoint $endpoint, DianSoapMessage $message): DianSoapHttpResponse;
}

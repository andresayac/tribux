<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Transport;

enum DianTransportFailure: string
{
    case Connection = 'connection';
    case ResponseTooLarge = 'response_too_large';
    case Timeout = 'timeout';
    case Tls = 'tls';
    case Unknown = 'unknown';
}

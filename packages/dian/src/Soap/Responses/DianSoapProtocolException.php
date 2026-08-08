<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

use RuntimeException;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Validation\XmlValidationError;

final class DianSoapProtocolException extends RuntimeException
{
    /** @param list<XmlValidationError> $xmlErrors */
    public function __construct(
        public readonly string $reason,
        public readonly DianSoapHttpResponse $response,
        public readonly array $xmlErrors = [],
    ) {
        parent::__construct('DIAN SOAP response violates the expected protocol: '.$reason);
    }
}

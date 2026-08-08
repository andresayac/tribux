<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Transport;

use RuntimeException;
use Throwable;

final class DianTransportException extends RuntimeException
{
    public function __construct(
        public readonly DianTransportFailure $failure,
        public readonly int $transportCode,
        public readonly string $originalMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('DIAN SOAP transport failed (%s, code %d).', $failure->value, $transportCode),
            $transportCode,
            $previous,
        );
    }
}

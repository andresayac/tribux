<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Transport;

use InvalidArgumentException;

final readonly class DianSoapHttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException('SOAP HTTP status code must be between 100 and 599.');
        }
    }

    /** @return list<string> */
    public function header(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Transport;

use CurlHandle;
use InvalidArgumentException;
use RuntimeException;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapMessage;

final readonly class CurlDianSoapTransport implements DianSoapTransport
{
    public function __construct(
        private int $connectTimeoutMilliseconds = 5_000,
        private int $requestTimeoutMilliseconds = 30_000,
        private int $maxResponseBytes = 10_485_760,
        private ?string $caBundlePath = null,
    ) {
        if ($connectTimeoutMilliseconds < 1) {
            throw new InvalidArgumentException('DIAN connect timeout must be at least one millisecond.');
        }

        if ($requestTimeoutMilliseconds < $connectTimeoutMilliseconds) {
            throw new InvalidArgumentException('DIAN request timeout cannot be shorter than the connect timeout.');
        }

        if ($maxResponseBytes < 1) {
            throw new InvalidArgumentException('DIAN maximum response size must be at least one byte.');
        }

        if ($caBundlePath !== null && ! is_file($caBundlePath)) {
            throw new InvalidArgumentException('DIAN CA bundle path must reference a readable file.');
        }
    }

    public function send(DianEndpoint $endpoint, DianSoapMessage $message): DianSoapHttpResponse
    {
        $handle = curl_init();

        if (! $handle instanceof CurlHandle) {
            throw new RuntimeException('Could not initialize cURL for DIAN SOAP transport.');
        }

        $responseBody = '';
        $responseHeaders = [];
        $responseTooLarge = false;
        $serviceUrl = $endpoint->serviceUrl;

        if ($serviceUrl === '') {
            throw new RuntimeException('DIAN SOAP endpoint cannot be empty.');
        }

        $options = [
            CURLOPT_URL => $serviceUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $message->xml,
            CURLOPT_HTTPHEADER => [
                'Accept: application/soap+xml',
                'Content-Type: '.$message->contentType(),
            ],
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMilliseconds,
            CURLOPT_TIMEOUT_MS => $this->requestTimeoutMilliseconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => function (CurlHandle $curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if (str_starts_with($trimmed, 'HTTP/')) {
                    $responseHeaders = [];

                    return $length;
                }

                if ($trimmed === '' || ! str_contains($trimmed, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $normalizedName = strtolower(trim($name));
                $responseHeaders[$normalizedName] ??= [];
                $responseHeaders[$normalizedName][] = trim($value);

                return $length;
            },
            CURLOPT_WRITEFUNCTION => function (CurlHandle $curl, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBytes) {
                    $responseTooLarge = true;

                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ];

        $caBundlePath = $this->caBundlePath;

        if ($caBundlePath !== null && $caBundlePath !== '') {
            $options[CURLOPT_CAINFO] = $caBundlePath;
        }

        try {
            if (! curl_setopt_array($handle, $options)) {
                throw new RuntimeException('Could not configure cURL for DIAN SOAP transport.');
            }

            $succeeded = curl_exec($handle);
            $transportCode = curl_errno($handle);
            $originalMessage = curl_error($handle);
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }

        if ($succeeded !== true) {
            throw new DianTransportException(
                failure: $responseTooLarge
                    ? DianTransportFailure::ResponseTooLarge
                    : $this->classifyFailure($transportCode),
                transportCode: $transportCode,
                originalMessage: $originalMessage,
            );
        }

        if (! is_int($statusCode)) {
            throw new DianTransportException(
                DianTransportFailure::Unknown,
                0,
                'cURL returned a non-integer HTTP response code.',
            );
        }

        return new DianSoapHttpResponse($statusCode, $responseHeaders, $responseBody);
    }

    private function classifyFailure(int $code): DianTransportFailure
    {
        if ($code === CURLE_OPERATION_TIMEDOUT) {
            return DianTransportFailure::Timeout;
        }

        if (in_array($code, [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true)) {
            return DianTransportFailure::Connection;
        }

        if (in_array($code, [
            CURLE_SSL_CACERT,
            CURLE_SSL_CACERT_BADFILE,
            CURLE_SSL_CERTPROBLEM,
            CURLE_SSL_CIPHER,
            CURLE_SSL_CONNECT_ERROR,
        ], true)) {
            return DianTransportFailure::Tls;
        }

        return DianTransportFailure::Unknown;
    }
}

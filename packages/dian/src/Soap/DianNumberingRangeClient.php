<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DateTimeImmutable;
use Tribux\Dian\Signing\SigningCredentials;
use Tribux\Dian\Soap\Requests\GetNumberingRangeRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\Responses\GetNumberingRangeResponse;
use Tribux\Dian\Soap\Transport\CurlDianSoapTransport;
use Tribux\Dian\Soap\Transport\DianSoapTransport;

/**
 * Reads the ranges DIAN has authorized for an issuer.
 *
 * This is a query, not an allocation: the answer describes what exists, and
 * turning it into an issued number is a separate, validated decision. The
 * response body carries the resolution technical key, so callers must treat it
 * as secret-bearing.
 */
final readonly class DianNumberingRangeClient
{
    public function __construct(
        private DianEndpoint $endpoint,
        private WsSecuritySoapEnvelopeBuilder $envelopeBuilder = new WsSecuritySoapEnvelopeBuilder,
        private DianSoapTransport $transport = new CurlDianSoapTransport,
        private DianSoapResponseParser $responseParser = new DianSoapResponseParser,
    ) {}

    public function get(
        GetNumberingRangeRequest $request,
        SigningCredentials $credentials,
        DateTimeImmutable $createdAt,
    ): GetNumberingRangeResponse {
        $message = $this->envelopeBuilder->build($this->endpoint, $request, $credentials, $createdAt);
        $httpResponse = $this->transport->send($this->endpoint, $message);

        return $this->responseParser->parseGetNumberingRange($httpResponse);
    }
}

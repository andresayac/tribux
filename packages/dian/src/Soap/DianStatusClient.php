<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DateTimeImmutable;
use Tribux\Dian\Signing\SigningCredentials;
use Tribux\Dian\Soap\Requests\GetStatusRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\Responses\GetStatusResponse;
use Tribux\Dian\Soap\Transport\CurlDianSoapTransport;
use Tribux\Dian\Soap\Transport\DianSoapTransport;

final readonly class DianStatusClient
{
    public function __construct(
        private DianEndpoint $endpoint,
        private WsSecuritySoapEnvelopeBuilder $envelopeBuilder = new WsSecuritySoapEnvelopeBuilder(),
        private DianSoapTransport $transport = new CurlDianSoapTransport(),
        private DianSoapResponseParser $responseParser = new DianSoapResponseParser(),
    ) {
    }

    public function get(
        string $trackId,
        SigningCredentials $credentials,
        DateTimeImmutable $createdAt,
    ): GetStatusResponse {
        $message = $this->envelopeBuilder->build(
            $this->endpoint,
            new GetStatusRequest($trackId),
            $credentials,
            $createdAt,
        );
        $httpResponse = $this->transport->send($this->endpoint, $message);

        return $this->responseParser->parseGetStatus($httpResponse);
    }
}

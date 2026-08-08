<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DateTimeImmutable;
use Tribux\Dian\Signing\SigningCredentials;
use Tribux\Dian\Soap\Requests\GetStatusZipRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\Responses\GetStatusZipResponse;
use Tribux\Dian\Soap\Transport\CurlDianSoapTransport;
use Tribux\Dian\Soap\Transport\DianSoapTransport;

final readonly class DianStatusZipClient
{
    public function __construct(
        private DianEndpoint $endpoint,
        private WsSecuritySoapEnvelopeBuilder $envelopeBuilder = new WsSecuritySoapEnvelopeBuilder(),
        private DianSoapTransport $transport = new CurlDianSoapTransport(),
        private DianSoapResponseParser $responseParser = new DianSoapResponseParser(),
    ) {
    }

    public function get(
        string $zipKey,
        SigningCredentials $credentials,
        DateTimeImmutable $createdAt,
    ): GetStatusZipResponse {
        $message = $this->envelopeBuilder->build(
            $this->endpoint,
            new GetStatusZipRequest($zipKey),
            $credentials,
            $createdAt,
        );
        $httpResponse = $this->transport->send($this->endpoint, $message);

        return $this->responseParser->parseGetStatusZip($httpResponse);
    }
}

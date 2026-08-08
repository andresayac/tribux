<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DateTimeImmutable;
use Tribux\Dian\Signing\SigningCredentials;
use Tribux\Dian\Soap\Requests\SendTestSetAsyncRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\Responses\SendTestSetAsyncResponse;
use Tribux\Dian\Soap\Transport\CurlDianSoapTransport;
use Tribux\Dian\Soap\Transport\DianSoapTransport;

final readonly class DianTestSetClient
{
    public function __construct(
        private DianEndpoint $endpoint,
        private WsSecuritySoapEnvelopeBuilder $envelopeBuilder = new WsSecuritySoapEnvelopeBuilder(),
        private DianSoapTransport $transport = new CurlDianSoapTransport(),
        private DianSoapResponseParser $responseParser = new DianSoapResponseParser(),
    ) {
    }

    public function send(
        SendTestSetAsyncRequest $request,
        SigningCredentials $credentials,
        DateTimeImmutable $createdAt,
    ): SendTestSetAsyncResponse {
        $message = $this->envelopeBuilder->build($this->endpoint, $request, $credentials, $createdAt);
        $httpResponse = $this->transport->send($this->endpoint, $message);

        return $this->responseParser->parseSendTestSetAsync($httpResponse);
    }
}

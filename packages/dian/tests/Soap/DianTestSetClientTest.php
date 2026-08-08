<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapMessage;
use Tribux\Dian\Soap\DianTestSetClient;
use Tribux\Dian\Soap\Requests\SendTestSetAsyncRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\SoapMessageIdGenerator;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Soap\Transport\DianSoapTransport;
use Tribux\Dian\Soap\WsSecuritySoapEnvelopeBuilder;
use Tribux\Dian\Tests\Support\EphemeralSigningCredentials;

final class DianTestSetClientTest extends TestCase
{
    #[Test]
    public function it_composes_signing_transport_and_response_parsing(): void
    {
        $endpoint = DianEndpoint::defaultFor(DianEnvironment::Habilitation);
        $transport = new RecordingSoapTransport(new DianSoapHttpResponse(
            202,
            [],
            $this->fixture('send-test-set-response.xml'),
        ));
        $client = new DianTestSetClient(
            $endpoint,
            new WsSecuritySoapEnvelopeBuilder(new FixedClientSoapIdGenerator(
                'message',
                'to',
                'timestamp',
                'token',
                'signature',
            )),
            $transport,
            new DianSoapResponseParser(),
        );
        $credentials = EphemeralSigningCredentials::create();
        $response = $client->send(
            new SendTestSetAsyncRequest('test.zip', 'synthetic zip', 'test-set-id'),
            $credentials,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        );

        self::assertSame('zip-track-id', $response->zipKey);
        self::assertSame($endpoint, $transport->endpoint);
        self::assertNotNull($transport->message);
        self::assertStringContainsString('<ds:Signature', $transport->message->xml);
        self::assertStringContainsString(base64_encode('synthetic zip'), $transport->message->xml);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/soap/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}

final class RecordingSoapTransport implements DianSoapTransport
{
    public ?DianEndpoint $endpoint = null;
    public ?DianSoapMessage $message = null;

    public function __construct(private readonly DianSoapHttpResponse $response)
    {
    }

    public function send(DianEndpoint $endpoint, DianSoapMessage $message): DianSoapHttpResponse
    {
        $this->endpoint = $endpoint;
        $this->message = $message;

        return $this->response;
    }
}

final class FixedClientSoapIdGenerator implements SoapMessageIdGenerator
{
    /** @var list<string> */
    private array $ids;

    public function __construct(string ...$ids)
    {
        $this->ids = array_values($ids);
    }

    public function generate(): string
    {
        $id = array_shift($this->ids);

        if (! is_string($id)) {
            throw new \RuntimeException('No SOAP client test ID remains.');
        }

        return $id;
    }
}

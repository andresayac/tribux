<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapMessage;
use Tribux\Dian\Soap\DianSoapOperation;
use Tribux\Dian\Soap\DianStatusZipClient;
use Tribux\Dian\Soap\Requests\GetStatusZipRequest;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\SoapMessageIdGenerator;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Soap\Transport\DianSoapTransport;
use Tribux\Dian\Soap\WsSecuritySoapEnvelopeBuilder;
use Tribux\Dian\Tests\Support\EphemeralSigningCredentials;

final class DianStatusZipClientTest extends TestCase
{
    #[Test]
    public function it_builds_get_status_zip_and_preserves_every_response_entry(): void
    {
        $endpoint = DianEndpoint::defaultFor(DianEnvironment::Habilitation);
        $transport = new RecordingStatusZipTransport(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-status-zip-response.xml'),
        ));
        $client = new DianStatusZipClient(
            $endpoint,
            new WsSecuritySoapEnvelopeBuilder(new FixedStatusZipSoapIdGenerator(
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

        $response = $client->get(
            'zip-track-id',
            $credentials,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        );

        self::assertFalse($response->isFault());
        self::assertSame(200, $response->httpStatusCode);
        self::assertCount(3, $response->results);
        self::assertTrue($response->results[0]?->isValid);
        self::assertSame([], $response->results[0]?->errorMessages);
        self::assertSame('synthetic-1', $response->results[0]?->statusCode);
        self::assertSame('<ApplicationResponse id="1"/>', $response->results[0]?->xmlBase64Bytes?->decoded);
        self::assertNull($response->results[0]?->xmlBytes);
        self::assertFalse($response->results[1]?->isValid);
        self::assertSame(['Original synthetic DIAN message'], $response->results[1]?->errorMessages);
        self::assertSame('Uninterpreted package member status', $response->results[1]?->statusMessage);
        self::assertNull($response->results[2]);
        self::assertSame($transport->response->body, $response->rawXml);
        self::assertNotNull($transport->message);
        self::assertSame(DianSoapOperation::GetStatusZip, $transport->message->operation);
        self::assertStringContainsString('<wcf:trackId>zip-track-id</wcf:trackId>', $transport->message->xml);
    }

    #[Test]
    public function it_preserves_a_nil_get_status_zip_result(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
              <s:Body>
                <GetStatusZipResponse xmlns="http://wcf.dian.colombia">
                  <GetStatusZipResult xmlns:i="http://www.w3.org/2001/XMLSchema-instance" i:nil="true"/>
                </GetStatusZipResponse>
              </s:Body>
            </s:Envelope>
            XML;

        $response = (new DianSoapResponseParser())->parseGetStatusZip(
            new DianSoapHttpResponse(200, [], $xml),
        );

        self::assertFalse($response->isFault());
        self::assertNull($response->results);
        self::assertSame($xml, $response->rawXml);
    }

    #[Test]
    public function it_parses_a_fault_for_get_status_zip(): void
    {
        $response = (new DianSoapResponseParser())->parseGetStatusZip(new DianSoapHttpResponse(
            500,
            [],
            $this->fixture('soap-12-fault.xml'),
        ));

        self::assertTrue($response->isFault());
        self::assertNull($response->results);
        self::assertSame('s:Sender', $response->fault?->code);
    }

    #[Test]
    public function it_rejects_an_empty_zip_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trackId cannot be empty');

        new GetStatusZipRequest('  ');
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/soap/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}

final class RecordingStatusZipTransport implements DianSoapTransport
{
    public ?DianEndpoint $endpoint = null;
    public ?DianSoapMessage $message = null;

    public function __construct(public readonly DianSoapHttpResponse $response)
    {
    }

    public function send(DianEndpoint $endpoint, DianSoapMessage $message): DianSoapHttpResponse
    {
        $this->endpoint = $endpoint;
        $this->message = $message;

        return $this->response;
    }
}

final class FixedStatusZipSoapIdGenerator implements SoapMessageIdGenerator
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
            throw new \RuntimeException('No ZIP status test ID remains.');
        }

        return $id;
    }
}

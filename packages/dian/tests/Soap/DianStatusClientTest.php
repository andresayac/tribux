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
use Tribux\Dian\Soap\DianStatusClient;
use Tribux\Dian\Soap\Requests\GetStatusRequest;
use Tribux\Dian\Soap\Responses\DianSoapProtocolException;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\SoapMessageIdGenerator;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Soap\Transport\DianSoapTransport;
use Tribux\Dian\Soap\WsSecuritySoapEnvelopeBuilder;
use Tribux\Dian\Tests\Support\EphemeralSigningCredentials;

final class DianStatusClientTest extends TestCase
{
    #[Test]
    public function it_builds_get_status_and_preserves_the_complete_dian_response(): void
    {
        $endpoint = DianEndpoint::defaultFor(DianEnvironment::Habilitation);
        $transport = new RecordingStatusTransport(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-status-response.xml'),
        ));
        $client = new DianStatusClient(
            $endpoint,
            new WsSecuritySoapEnvelopeBuilder(new FixedStatusSoapIdGenerator(
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
        self::assertNotNull($response->result);
        self::assertFalse($response->result->isValid);
        self::assertSame(['Original DIAN validation message', null], $response->result->errorMessages);
        self::assertSame('90', $response->result->statusCode);
        self::assertSame('Validation finished', $response->result->statusDescription);
        self::assertSame('Document rejected by a synthetic rule', $response->result->statusMessage);
        self::assertSame('<ApplicationResponse/>', $response->result->xmlBase64Bytes?->decoded);
        self::assertSame('<Invoice/>', $response->result->xmlBytes?->decoded);
        self::assertStringContainsString("\n", $response->result->xmlBytes?->encoded ?? '');
        self::assertSame('document-key', $response->result->xmlDocumentKey);
        self::assertSame('fv0000001230002600000001.xml', $response->result->xmlFileName);
        self::assertSame($transport->response->body, $response->rawXml);
        self::assertNotNull($transport->message);
        self::assertSame(DianSoapOperation::GetStatus, $transport->message->operation);
        self::assertStringContainsString('<wcf:trackId>zip-track-id</wcf:trackId>', $transport->message->xml);
    }

    #[Test]
    public function it_parses_a_fault_for_get_status(): void
    {
        $response = (new DianSoapResponseParser())->parseGetStatus(new DianSoapHttpResponse(
            500,
            [],
            $this->fixture('soap-12-fault.xml'),
        ));

        self::assertTrue($response->isFault());
        self::assertSame(500, $response->httpStatusCode);
        self::assertNull($response->result);
        self::assertSame('s:Sender', $response->fault?->code);
    }

    #[Test]
    public function it_preserves_a_nil_get_status_result(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
              <s:Body>
                <GetStatusResponse xmlns="http://wcf.dian.colombia">
                  <GetStatusResult xmlns:i="http://www.w3.org/2001/XMLSchema-instance" i:nil="true"/>
                </GetStatusResponse>
              </s:Body>
            </s:Envelope>
            XML;

        $response = (new DianSoapResponseParser())->parseGetStatus(
            new DianSoapHttpResponse(200, [], $xml),
        );

        self::assertFalse($response->isFault());
        self::assertNull($response->result);
        self::assertSame($xml, $response->rawXml);
    }

    #[Test]
    public function it_rejects_an_empty_track_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trackId cannot be empty');

        new GetStatusRequest('  ');
    }

    #[Test]
    public function it_rejects_invalid_base64_from_dian(): void
    {
        $xml = str_replace(
            'PEFwcGxpY2F0aW9uUmVzcG9uc2UvPg==',
            '**invalid-base64**',
            $this->fixture('get-status-response.xml'),
        );

        $this->expectException(DianSoapProtocolException::class);
        $this->expectExceptionMessage('base64Binary has an invalid lexical value');

        (new DianSoapResponseParser())->parseGetStatus(new DianSoapHttpResponse(200, [], $xml));
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/soap/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}

final class RecordingStatusTransport implements DianSoapTransport
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

final class FixedStatusSoapIdGenerator implements SoapMessageIdGenerator
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
            throw new \RuntimeException('No status test ID remains.');
        }

        return $id;
    }
}

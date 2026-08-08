<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianNumberingRangeClient;
use Tribux\Dian\Soap\DianSoapMessage;
use Tribux\Dian\Soap\DianSoapOperation;
use Tribux\Dian\Soap\Requests\GetNumberingRangeRequest;
use Tribux\Dian\Soap\Responses\DianSoapProtocolException;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\SoapMessageIdGenerator;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Soap\Transport\DianSoapTransport;
use Tribux\Dian\Soap\WsSecuritySoapEnvelopeBuilder;
use Tribux\Dian\Tests\Support\EphemeralSigningCredentials;

final class DianNumberingRangeClientTest extends TestCase
{
    #[Test]
    public function it_builds_the_query_and_preserves_every_authorized_range(): void
    {
        $transport = new RecordingNumberingRangeTransport(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-numbering-range-response.xml'),
        ));
        $client = new DianNumberingRangeClient(
            DianEndpoint::defaultFor(DianEnvironment::Habilitation),
            new WsSecuritySoapEnvelopeBuilder(new FixedNumberingRangeSoapIdGenerator(
                'message',
                'to',
                'timestamp',
                'token',
                'signature',
            )),
            $transport,
            new DianSoapResponseParser,
        );
        $credentials = EphemeralSigningCredentials::create();

        $response = $client->get(
            new GetNumberingRangeRequest('900123456', '900123456', '00000000-0000-4000-8000-000000000001'),
            $credentials,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        );

        self::assertFalse($response->isFault());
        self::assertSame(200, $response->httpStatusCode);
        self::assertSame('100', $response->result?->operationCode);
        self::assertSame('Synthetic operation description', $response->result?->operationDescription);
        self::assertCount(3, $response->result?->ranges ?? []);

        $first = $response->result?->ranges[0] ?? null;
        self::assertNotNull($first);
        self::assertSame('18760000001', $first->resolutionNumber);
        self::assertSame('SETP', $first->prefix);
        self::assertSame(1, $first->fromNumber);
        self::assertSame(5000000, $first->toNumber);
        self::assertSame('2027-12-31', $first->validDateTo);

        $second = $response->result?->ranges[1] ?? null;
        self::assertNotNull($second);
        self::assertNull($second->resolutionNumber);
        self::assertSame(0, $second->fromNumber);
        self::assertFalse($second->hasTechnicalKey());

        $ranges = $response->result?->ranges;
        self::assertIsArray($ranges);
        self::assertArrayHasKey(2, $ranges);
        self::assertNull($ranges[2], 'A nil member is preserved as a null entry, not dropped.');

        self::assertNotNull($transport->message);
        self::assertSame(DianSoapOperation::GetNumberingRange, $transport->message->operation);
        self::assertStringContainsString('<wcf:accountCode>900123456</wcf:accountCode>', $transport->message->xml);
        self::assertStringContainsString(
            '<wcf:softwareCode>00000000-0000-4000-8000-000000000001</wcf:softwareCode>',
            $transport->message->xml,
        );
    }

    #[Test]
    public function the_technical_key_is_readable_but_never_leaks_by_accident(): void
    {
        $response = (new DianSoapResponseParser)->parseGetNumberingRange(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-numbering-range-response.xml'),
        ));
        $range = $response->result?->ranges[0] ?? null;
        self::assertNotNull($range);

        self::assertTrue($range->hasTechnicalKey());
        self::assertSame('synthetic-technical-key', $range->technicalKey());
        self::assertStringNotContainsString('synthetic-technical-key', print_r($range, true));
        self::assertStringContainsString('[redacted]', print_r($range, true));

        // The public range description encodes; the private key does not.
        $encoded = (string) json_encode($range);
        self::assertStringContainsString('"resolutionNumber":"18760000001"', $encoded);
        self::assertStringNotContainsString('synthetic-technical-key', $encoded);
        self::assertStringNotContainsString('technicalKey', $encoded);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must not be serialized');

        serialize($range);
    }

    #[Test]
    public function it_preserves_a_nil_response_list_without_inventing_an_empty_one(): void
    {
        $response = (new DianSoapResponseParser)->parseGetNumberingRange(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-numbering-range-empty-response.xml'),
        ));

        self::assertSame('404', $response->result?->operationCode);
        self::assertNull($response->result?->operationDescription);
        self::assertNull($response->result?->ranges, 'A nil list is not the same as an empty list.');
    }

    #[Test]
    public function it_preserves_a_nil_result(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
              <s:Body>
                <GetNumberingRangeResponse xmlns="http://wcf.dian.colombia">
                  <GetNumberingRangeResult xmlns:i="http://www.w3.org/2001/XMLSchema-instance" i:nil="true"/>
                </GetNumberingRangeResponse>
              </s:Body>
            </s:Envelope>
            XML;

        $response = (new DianSoapResponseParser)->parseGetNumberingRange(new DianSoapHttpResponse(200, [], $xml));

        self::assertFalse($response->isFault());
        self::assertNull($response->result);
        self::assertSame($xml, $response->rawXml);
    }

    #[Test]
    public function it_parses_a_fault_for_the_numbering_query(): void
    {
        $response = (new DianSoapResponseParser)->parseGetNumberingRange(new DianSoapHttpResponse(
            500,
            [],
            $this->fixture('soap-12-fault.xml'),
        ));

        self::assertTrue($response->isFault());
        self::assertNull($response->result);
        self::assertSame(500, $response->httpStatusCode);
        self::assertSame('s:Sender', $response->fault?->code);
    }

    #[Test]
    public function it_refuses_a_non_numeric_range_boundary(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
              <s:Body>
                <GetNumberingRangeResponse xmlns="http://wcf.dian.colombia">
                  <GetNumberingRangeResult xmlns:a="http://schemas.datacontract.org/2004/07/NumberRangeResponseList"
                                           xmlns:b="http://schemas.datacontract.org/2004/07/NumberRangeResponse">
                    <a:OperationCode>100</a:OperationCode>
                    <a:ResponseList>
                      <b:NumberRangeResponse>
                        <b:FromNumber>one</b:FromNumber>
                      </b:NumberRangeResponse>
                    </a:ResponseList>
                  </GetNumberingRangeResult>
                </GetNumberingRangeResponse>
              </s:Body>
            </s:Envelope>
            XML;

        $this->expectException(DianSoapProtocolException::class);
        $this->expectExceptionMessage('SOAP long has an invalid lexical value');

        (new DianSoapResponseParser)->parseGetNumberingRange(new DianSoapHttpResponse(200, [], $xml));
    }

    #[Test]
    public function a_null_argument_is_sent_as_an_explicit_nil(): void
    {
        $transport = new RecordingNumberingRangeTransport(new DianSoapHttpResponse(
            200,
            [],
            $this->fixture('get-numbering-range-empty-response.xml'),
        ));
        $client = new DianNumberingRangeClient(
            DianEndpoint::defaultFor(DianEnvironment::Habilitation),
            new WsSecuritySoapEnvelopeBuilder(new FixedNumberingRangeSoapIdGenerator(
                'message',
                'to',
                'timestamp',
                'token',
                'signature',
            )),
            $transport,
            new DianSoapResponseParser,
        );
        $credentials = EphemeralSigningCredentials::create();

        $client->get(
            new GetNumberingRangeRequest('900123456'),
            $credentials,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        );

        self::assertNotNull($transport->message);
        self::assertStringContainsString('<wcf:accountCodeT', $transport->message->xml);
        self::assertStringContainsString('nil="true"', $transport->message->xml);
    }

    #[Test]
    public function it_rejects_a_blank_argument_that_is_neither_a_value_nor_a_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accountCode must be a non-empty value or null');

        new GetNumberingRangeRequest('   ');
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/soap/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}

final class RecordingNumberingRangeTransport implements DianSoapTransport
{
    public ?DianEndpoint $endpoint = null;

    public ?DianSoapMessage $message = null;

    public function __construct(public readonly DianSoapHttpResponse $response) {}

    public function send(DianEndpoint $endpoint, DianSoapMessage $message): DianSoapHttpResponse
    {
        $this->endpoint = $endpoint;
        $this->message = $message;

        return $this->response;
    }
}

final class FixedNumberingRangeSoapIdGenerator implements SoapMessageIdGenerator
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
            throw new RuntimeException('No numbering range test ID remains.');
        }

        return $id;
    }
}

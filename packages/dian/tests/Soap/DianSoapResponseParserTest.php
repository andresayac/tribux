<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Soap\Responses\DianSoapProtocolException;
use Tribux\Dian\Soap\Responses\DianSoapResponseParser;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;

final class DianSoapResponseParserTest extends TestCase
{
    #[Test]
    public function it_parses_the_upload_response_without_losing_message_context(): void
    {
        $httpResponse = new DianSoapHttpResponse(
            202,
            ['content-type' => ['application/soap+xml']],
            $this->fixture('send-test-set-response.xml'),
        );
        $response = (new DianSoapResponseParser())->parseSendTestSetAsync($httpResponse);

        self::assertFalse($response->isFault());
        self::assertSame(202, $response->httpStatusCode);
        self::assertSame('zip-track-id', $response->zipKey);
        self::assertSame($httpResponse->body, $response->rawXml);
        self::assertCount(2, $response->messages);
        self::assertTrue($response->messages[0]->success);
        self::assertSame('document-key-1', $response->messages[0]->documentKey);
        self::assertSame('Batch queued by DIAN', $response->messages[0]->processedMessage);
        self::assertSame('00', $response->messages[0]->senderCode);
        self::assertSame('fv0000001230002600000001.xml', $response->messages[0]->xmlFileName);
        self::assertFalse($response->messages[1]->success);
        self::assertNull($response->messages[1]->documentKey);
        self::assertSame('Original rejection context', $response->messages[1]->processedMessage);
        self::assertNull($response->messages[1]->xmlFileName);
    }

    #[Test]
    public function it_preserves_a_soap_12_fault_even_on_http_500(): void
    {
        $httpResponse = new DianSoapHttpResponse(500, [], $this->fixture('soap-12-fault.xml'));
        $response = (new DianSoapResponseParser())->parseSendTestSetAsync($httpResponse);

        self::assertTrue($response->isFault());
        self::assertNull($response->zipKey);
        self::assertSame([], $response->messages);
        self::assertNotNull($response->fault);
        self::assertSame('s:Sender', $response->fault->code);
        self::assertSame('wcf:InvalidSecurity', $response->fault->subcode);
        self::assertSame([
            ['language' => 'es-CO', 'text' => 'La firma de seguridad no es válida.'],
            ['language' => 'en-US', 'text' => 'The security signature is invalid.'],
        ], $response->fault->reasons);
        self::assertIsString($response->fault->detailXml);
        self::assertStringContainsString('SEC-001', $response->fault->detailXml);
        self::assertSame($httpResponse->body, $response->rawXml);
    }

    #[Test]
    public function it_rejects_malformed_xml_and_preserves_libxml_errors(): void
    {
        $httpResponse = new DianSoapHttpResponse(502, [], '<s:Envelope><broken></s:Envelope>');

        try {
            (new DianSoapResponseParser())->parseSendTestSetAsync($httpResponse);
            self::fail('Malformed SOAP XML must fail.');
        } catch (DianSoapProtocolException $exception) {
            self::assertSame($httpResponse, $exception->response);
            self::assertNotEmpty($exception->xmlErrors);
            self::assertGreaterThan(0, $exception->xmlErrors[0]->code);
            self::assertStringNotContainsString($httpResponse->body, $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_an_invalid_boolean_value(): void
    {
        $xml = str_replace(
            '<b:Success>true</b:Success>',
            '<b:Success>yes</b:Success>',
            $this->fixture('send-test-set-response.xml'),
        );

        $this->expectException(DianSoapProtocolException::class);
        $this->expectExceptionMessage('invalid lexical value');

        (new DianSoapResponseParser())->parseSendTestSetAsync(new DianSoapHttpResponse(200, [], $xml));
    }

    #[Test]
    public function it_preserves_an_omitted_optional_success_value_as_null(): void
    {
        $xml = str_replace(
            '<b:Success>true</b:Success>',
            '',
            $this->fixture('send-test-set-response.xml'),
        );
        $response = (new DianSoapResponseParser())->parseSendTestSetAsync(
            new DianSoapHttpResponse(200, [], $xml),
        );

        self::assertNull($response->messages[0]->success);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/soap/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}

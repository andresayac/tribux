<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use JsonException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Signing\SigningCredentials;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapOperation;
use Tribux\Dian\Soap\Requests\SendTestSetAsyncRequest;
use Tribux\Dian\Soap\SoapCertificateReference;
use Tribux\Dian\Soap\SoapMessageIdGenerator;
use Tribux\Dian\Soap\WsSecuritySoapEnvelopeBuilder;

final class WsSecuritySoapEnvelopeBuilderTest extends TestCase
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const string NS_SOAP = 'http://www.w3.org/2003/05/soap-envelope';
    private const string NS_WSA = 'http://www.w3.org/2005/08/addressing';
    private const string NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const string NS_WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

    #[Test]
    public function it_builds_and_signs_the_current_dian_ws_security_profile(): void
    {
        $credentials = $this->ephemeralCredentials();
        $createdAt = new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1));
        $builder = new WsSecuritySoapEnvelopeBuilder(new FixedSoapMessageIdGenerator(
            'message',
            'to',
            'timestamp',
            'token',
            'signature',
        ));
        $request = new SendTestSetAsyncRequest(
            fileName: 'z0000001230002600000001.zip',
            zipContents: "PK\x03\x04synthetic-zip",
            testSetId: '00000000-0000-0000-0000-000000000001',
        );
        $message = $builder->build(
            DianEndpoint::defaultFor(DianEnvironment::Habilitation),
            $request,
            $credentials,
            $createdAt,
        );
        $document = $this->document($message->xml);
        $xpath = $this->xpath($document);
        $profile = $this->profile();

        self::assertSame(DianSoapOperation::SendTestSetAsync, $message->operation);
        self::assertSame(
            'application/soap+xml; charset=utf-8; action="http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync"',
            $message->contentType(),
        );
        self::assertSame(
            DianSoapOperation::SendTestSetAsync->action(),
            $xpath->evaluate('string(/s:Envelope/s:Header/wsa:Action)'),
        );
        self::assertSame('urn:uuid:message', $xpath->evaluate('string(//wsa:MessageID)'));
        self::assertSame('id-to', $xpath->evaluate('string(//wsa:To/@wsu:Id)'));
        self::assertSame(
            'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
            $xpath->evaluate('string(//wsa:To)'),
        );
        self::assertSame('TS-timestamp', $xpath->evaluate('string(//wsu:Timestamp/@wsu:Id)'));
        $utc = new DateTimeZone('UTC');
        self::assertSame(
            $createdAt->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
            $xpath->evaluate('string(//wsu:Created)'),
        );
        self::assertSame(
            $createdAt->modify('+60 seconds')->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
            $xpath->evaluate('string(//wsu:Expires)'),
        );
        self::assertSame('X509-token', $xpath->evaluate('string(//wsse:BinarySecurityToken/@wsu:Id)'));
        self::assertSame('SIG-signature', $xpath->evaluate('string(//ds:Signature/@Id)'));
        self::assertSame(
            $profile['signature_method'],
            $xpath->evaluate('string(//ds:SignatureMethod/@Algorithm)'),
        );
        self::assertSame(
            $profile['canonicalization_method'],
            $xpath->evaluate('string(//ds:CanonicalizationMethod/@Algorithm)'),
        );
        self::assertSame(
            $profile['digest_method'],
            $xpath->evaluate('string(//ds:DigestMethod/@Algorithm)'),
        );
        self::assertSame(
            'z0000001230002600000001.zip',
            $xpath->evaluate('string(/s:Envelope/s:Body/wcf:SendTestSetAsync/wcf:fileName)'),
        );
        self::assertSame(
            "PK\x03\x04synthetic-zip",
            base64_decode((string) $xpath->evaluate('string(//wcf:contentFile)'), true),
        );
        self::assertSame(
            $credentials->certificate->sha1ThumbprintBase64(),
            $xpath->evaluate('string(//wsse:SecurityTokenReference/wsse:KeyIdentifier)'),
        );
        self::assertSame(
            $credentials->certificate->base64,
            $xpath->evaluate('string(//wsse:BinarySecurityToken)'),
        );

        $signedInfo = $this->oneElement($xpath, '//ds:SignedInfo');
        $signatureValue = $xpath->evaluate('string(//ds:SignatureValue)');
        self::assertIsString($signatureValue);
        $signature = base64_decode($signatureValue, true);
        self::assertIsString($signature);
        self::assertSame(
            1,
            openssl_verify($this->canonicalize($signedInfo), $signature, $credentials->certificate->resource(), OPENSSL_ALGO_SHA256),
        );
        self::assertSame(
            base64_encode(hash('sha256', $this->canonicalize($this->oneElement($xpath, '//wsa:To')), true)),
            $xpath->evaluate('string(//ds:Reference[@URI="#id-to"]/ds:DigestValue)'),
        );
    }

    #[Test]
    public function it_can_use_the_binary_token_reference_shown_by_the_official_guide(): void
    {
        $credentials = $this->ephemeralCredentials();
        $builder = new WsSecuritySoapEnvelopeBuilder(
            new FixedSoapMessageIdGenerator('message', 'to', 'timestamp', 'token', 'signature'),
            SoapCertificateReference::BinarySecurityToken,
        );
        $message = $builder->build(
            DianEndpoint::defaultFor(DianEnvironment::Habilitation),
            new SendTestSetAsyncRequest('test.zip', 'zip', 'test-set'),
            $credentials,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        );
        $xpath = $this->xpath($this->document($message->xml));

        self::assertSame(
            '#X509-token',
            $xpath->evaluate('string(//wsse:SecurityTokenReference/wsse:Reference/@URI)'),
        );
        self::assertSame('0', $xpath->evaluate('string(count(//wsse:KeyIdentifier))'));
    }

    #[Test]
    public function it_rejects_an_empty_submission_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ZIP contents cannot be empty');

        new SendTestSetAsyncRequest('test.zip', '', 'test-set');
    }

    #[Test]
    public function it_rejects_a_non_positive_security_ttl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be at least one millisecond');

        new WsSecuritySoapEnvelopeBuilder(timeToLiveMilliseconds: 0);
    }

    private function ephemeralCredentials(): SigningCredentials
    {
        $options = $this->opensslOptions();
        $key = openssl_pkey_new($options);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new \RuntimeException('OpenSSL could not generate an ephemeral RSA key.');
        }

        $requestKey = $key;
        $request = openssl_csr_new([
            'countryName' => 'CO',
            'organizationName' => 'Tribux SOAP Test',
            'commonName' => 'Ephemeral SOAP Signer',
        ], $requestKey, $options);

        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            throw new \RuntimeException('OpenSSL could not generate an ephemeral certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 2, $options, 161803);

        if (! $certificate instanceof \OpenSSLCertificate) {
            throw new \RuntimeException('OpenSSL could not self-sign an ephemeral certificate.');
        }

        $privateKeyPem = '';
        $certificatePem = '';

        if (
            ! openssl_pkey_export($key, $privateKeyPem, null, $options)
            || ! is_string($privateKeyPem)
            || ! openssl_x509_export($certificate, $certificatePem)
            || ! is_string($certificatePem)
        ) {
            throw new \RuntimeException('OpenSSL could not export ephemeral signing material.');
        }

        return SigningCredentials::fromPem($privateKeyPem, $certificatePem);
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/fev-1.9/soap/ws-security-profile.json');
        self::assertIsString($contents);

        try {
            $profile = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($profile);

        return $profile;
    }

    /** @return array<string, int|string> */
    private function opensslOptions(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];
        $windowsConfiguration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (is_file($windowsConfiguration)) {
            $options['config'] = $windowsConfiguration;
        }

        return $options;
    }

    private function document(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));

        return $document;
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);

        foreach ([
            'ds' => self::NS_DS,
            's' => self::NS_SOAP,
            'wsa' => self::NS_WSA,
            'wsse' => self::NS_WSSE,
            'wsu' => self::NS_WSU,
            'wcf' => 'http://wcf.dian.colombia',
        ] as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath;
    }

    private function oneElement(DOMXPath $xpath, string $query): DOMElement
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length);
        $element = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $element);

        return $element;
    }

    private function canonicalize(DOMElement $element): string
    {
        $canonical = $element->C14N(true, false);
        self::assertIsString($canonical);

        return $canonical;
    }
}

final class FixedSoapMessageIdGenerator implements SoapMessageIdGenerator
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
            throw new \RuntimeException('No SOAP test ID remains.');
        }

        return $id;
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use RuntimeException;
use Tribux\Dian\Signing\SigningCredentials;

/**
 * Builds the SOAP 1.2 + WS-Addressing + WS-Security profile declared by the
 * current WcfDianCustomerServices WSDL.
 *
 * The X.509 supporting token signs the WS-Addressing To header. Transport,
 * retries and response parsing remain separate concerns.
 */
final readonly class WsSecuritySoapEnvelopeBuilder
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const string NS_SOAP = 'http://www.w3.org/2003/05/soap-envelope';
    private const string NS_WSA = 'http://www.w3.org/2005/08/addressing';
    private const string NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const string NS_WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const string NS_XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const string EXCLUSIVE_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    private const string RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const string SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const string BASE64_ENCODING = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    private const string X509_V3 = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';
    private const string THUMBPRINT_SHA1 = 'http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbprintSHA1';
    private const string ANONYMOUS_REPLY = 'http://www.w3.org/2005/08/addressing/anonymous';

    public function __construct(
        private SoapMessageIdGenerator $idGenerator = new RandomSoapMessageIdGenerator(),
        private SoapCertificateReference $certificateReference = SoapCertificateReference::ThumbprintSha1,
        private int $timeToLiveMilliseconds = 60_000,
    ) {
        if ($timeToLiveMilliseconds < 1) {
            throw new InvalidArgumentException('SOAP security timestamp TTL must be at least one millisecond.');
        }
    }

    public function build(
        DianEndpoint $endpoint,
        DianSoapBody $body,
        SigningCredentials $credentials,
        DateTimeImmutable $createdAt,
    ): DianSoapMessage {
        $credentials->assertValidAt($createdAt);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $envelope = $document->createElementNS(self::NS_SOAP, 's:Envelope');
        $document->appendChild($envelope);
        $this->declareNamespaces($envelope);
        $header = $this->append($envelope, self::NS_SOAP, 's:Header');
        $this->append($header, self::NS_WSA, 'wsa:Action', $body->operation()->action(), [
            's:mustUnderstand' => '1',
        ]);
        $messageId = 'urn:uuid:'.$this->idGenerator->generate();
        $this->append($header, self::NS_WSA, 'wsa:MessageID', $messageId);
        $replyTo = $this->append($header, self::NS_WSA, 'wsa:ReplyTo');
        $this->append($replyTo, self::NS_WSA, 'wsa:Address', self::ANONYMOUS_REPLY);
        $toId = 'id-'.$this->idGenerator->generate();
        $to = $this->append($header, self::NS_WSA, 'wsa:To', $endpoint->serviceUrl, [
            's:mustUnderstand' => '1',
            'wsu:Id' => $toId,
        ]);
        $security = $this->append($header, self::NS_WSSE, 'wsse:Security', null, [
            's:mustUnderstand' => '1',
        ]);
        $this->appendTimestamp($security, $createdAt);
        $tokenId = 'X509-'.$this->idGenerator->generate();
        $this->append($security, self::NS_WSSE, 'wsse:BinarySecurityToken', $credentials->certificate->base64, [
            'EncodingType' => self::BASE64_ENCODING,
            'ValueType' => self::X509_V3,
            'wsu:Id' => $tokenId,
        ]);
        $this->appendSignature($security, $to, $toId, $tokenId, $credentials);
        $soapBody = $this->append($envelope, self::NS_SOAP, 's:Body');
        $body->appendTo($soapBody);
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Could not serialize the DIAN SOAP message.');
        }

        return new DianSoapMessage($body->operation(), $xml);
    }

    private function declareNamespaces(DOMElement $envelope): void
    {
        foreach ([
            'ds' => self::NS_DS,
            'wsa' => self::NS_WSA,
            'wsse' => self::NS_WSSE,
            'wsu' => self::NS_WSU,
        ] as $prefix => $namespace) {
            $envelope->setAttributeNS(self::NS_XMLNS, 'xmlns:'.$prefix, $namespace);
        }
    }

    private function appendTimestamp(DOMElement $security, DateTimeImmutable $createdAt): void
    {
        $timestamp = $this->append($security, self::NS_WSU, 'wsu:Timestamp', null, [
            'wsu:Id' => 'TS-'.$this->idGenerator->generate(),
        ]);
        $expiresAt = $createdAt->modify(sprintf('+%d microseconds', $this->timeToLiveMilliseconds * 1_000));

        if (! $expiresAt instanceof DateTimeImmutable) {
            throw new RuntimeException('Could not calculate the SOAP security timestamp expiration.');
        }

        $utc = new DateTimeZone('UTC');
        $this->append($timestamp, self::NS_WSU, 'wsu:Created', $createdAt->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'));
        $this->append($timestamp, self::NS_WSU, 'wsu:Expires', $expiresAt->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'));
    }

    private function appendSignature(
        DOMElement $security,
        DOMElement $to,
        string $toId,
        string $tokenId,
        SigningCredentials $credentials,
    ): void {
        $signature = $this->append($security, self::NS_DS, 'ds:Signature', null, [
            'Id' => 'SIG-'.$this->idGenerator->generate(),
        ]);
        $signedInfo = $this->append($signature, self::NS_DS, 'ds:SignedInfo');
        $this->append($signedInfo, self::NS_DS, 'ds:CanonicalizationMethod', null, [
            'Algorithm' => self::EXCLUSIVE_C14N,
        ]);
        $this->append($signedInfo, self::NS_DS, 'ds:SignatureMethod', null, [
            'Algorithm' => self::RSA_SHA256,
        ]);
        $reference = $this->append($signedInfo, self::NS_DS, 'ds:Reference', null, ['URI' => '#'.$toId]);
        $transforms = $this->append($reference, self::NS_DS, 'ds:Transforms');
        $this->append($transforms, self::NS_DS, 'ds:Transform', null, ['Algorithm' => self::EXCLUSIVE_C14N]);
        $this->append($reference, self::NS_DS, 'ds:DigestMethod', null, ['Algorithm' => self::SHA256]);
        $this->append($reference, self::NS_DS, 'ds:DigestValue', $this->sha256($this->canonicalize($to)));
        $this->append(
            $signature,
            self::NS_DS,
            'ds:SignatureValue',
            base64_encode($credentials->signSha256($this->canonicalize($signedInfo))),
        );
        $keyInfo = $this->append($signature, self::NS_DS, 'ds:KeyInfo');
        $tokenReference = $this->append($keyInfo, self::NS_WSSE, 'wsse:SecurityTokenReference');

        if ($this->certificateReference === SoapCertificateReference::BinarySecurityToken) {
            $this->append($tokenReference, self::NS_WSSE, 'wsse:Reference', null, [
                'URI' => '#'.$tokenId,
                'ValueType' => self::X509_V3,
            ]);

            return;
        }

        $this->append(
            $tokenReference,
            self::NS_WSSE,
            'wsse:KeyIdentifier',
            $credentials->certificate->sha1ThumbprintBase64(),
            [
                'EncodingType' => self::BASE64_ENCODING,
                'ValueType' => self::THUMBPRINT_SHA1,
            ],
        );
    }

    private function canonicalize(DOMNode $node): string
    {
        $canonical = $node->C14N(true, false);

        if ($canonical === false) {
            throw new RuntimeException('Could not canonicalize a SOAP signature node.');
        }

        return $canonical;
    }

    private function sha256(string $value): string
    {
        return base64_encode(hash('sha256', $value, true));
    }

    /** @param array<string, string> $attributes */
    private function append(
        DOMNode $parent,
        string $namespace,
        string $qualifiedName,
        ?string $value = null,
        array $attributes = [],
    ): DOMElement {
        $document = $parent instanceof DOMDocument ? $parent : $parent->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('Cannot append a SOAP element without an owner document.');
        }

        $element = $document->createElementNS($namespace, $qualifiedName);

        if ($value !== null) {
            $element->appendChild($document->createTextNode($value));
        }

        foreach ($attributes as $name => $attributeValue) {
            if (str_contains($name, ':')) {
                [$prefix] = explode(':', $name, 2);
                $attributeNamespace = match ($prefix) {
                    's' => self::NS_SOAP,
                    'wsu' => self::NS_WSU,
                    default => throw new RuntimeException('Unsupported SOAP attribute prefix: '.$prefix),
                };
                $element->setAttributeNS($attributeNamespace, $name, $attributeValue);
                continue;
            }

            $element->setAttribute($name, $attributeValue);
        }

        $parent->appendChild($element);

        return $element;
    }
}

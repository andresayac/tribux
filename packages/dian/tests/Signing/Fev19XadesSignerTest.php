<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Signing;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Signing\DianSignaturePolicy;
use Tribux\Dian\Signing\DianSignerRole;
use Tribux\Dian\Signing\Fev19XadesSigner;
use Tribux\Dian\Signing\SignatureIdGenerator;
use Tribux\Dian\Signing\SigningCredentials;

final class Fev19XadesSignerTest extends TestCase
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const string NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const string NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    #[Test]
    public function it_creates_a_verifiable_xades_epes_signature(): void
    {
        $credentials = $this->ephemeralCredentials();
        $signingTime = (new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)))
            ->setTimezone(new DateTimeZone('America/Bogota'));
        $signer = new Fev19XadesSigner(new FixedSignatureIdGenerator('signature', 'certificate'));
        $xml = $signer->sign($this->unsignedInvoice(), $credentials, DianSignerRole::Supplier, $signingTime);
        $document = $this->document($xml);
        $xpath = $this->xpath($document);

        self::assertSame('2', $xpath->evaluate('string(count(/inv:Invoice/ext:UBLExtensions/ext:UBLExtension))'));
        self::assertSame('1', $xpath->evaluate('string(count(//ds:Signature))'));
        self::assertSame('3', $xpath->evaluate('string(count(//ds:SignedInfo/ds:Reference))'));
        self::assertSame('xmldsig-signature', $xpath->evaluate('string(//ds:Signature/@Id)'));
        self::assertSame(
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            $xpath->evaluate('string(//ds:SignatureMethod/@Algorithm)'),
        );
        self::assertSame(
            DianSignaturePolicy::version2Sha384()->identifier,
            $xpath->evaluate('string(//xades:SignaturePolicyIdentifier//xades:Identifier)'),
        );
        self::assertSame('supplier', $xpath->evaluate('string(//xades:ClaimedRole)'));
        self::assertSame(
            $signingTime->format('Y-m-d\TH:i:s.vP'),
            $xpath->evaluate('string(//xades:SigningTime)'),
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
            $this->digestDocumentWithoutSignature($document, $xpath),
            $xpath->evaluate('string(//ds:Reference[@URI=""]/ds:DigestValue)'),
        );
        self::assertSame(
            $this->sha384($this->canonicalize($this->oneElement($xpath, '//ds:KeyInfo'))),
            $xpath->evaluate('string(//ds:Reference[starts-with(@URI, "#xmldsig-certificate")]/ds:DigestValue)'),
        );
        self::assertSame(
            $this->sha384($this->canonicalize($this->oneElement($xpath, '//xades:SignedProperties'))),
            $xpath->evaluate('string(//ds:Reference[@Type="http://uri.etsi.org/01903#SignedProperties"]/ds:DigestValue)'),
        );
        self::assertSame(
            $this->sha384($credentials->certificate->der),
            $xpath->evaluate('string(//xades:SigningCertificate/xades:Cert/xades:CertDigest/ds:DigestValue)'),
        );
        self::assertSame(
            $credentials->certificate->issuerName,
            $xpath->evaluate('string(//ds:X509IssuerName)'),
        );
        self::assertStringStartsWith('C=CO,', $credentials->certificate->issuerName);
    }

    #[Test]
    public function it_imports_an_ephemeral_pkcs12_without_exposing_private_material(): void
    {
        [$key, $certificate] = $this->ephemeralKeyPair();
        $pkcs12 = '';
        self::assertTrue(openssl_pkcs12_export($certificate, $pkcs12, $key, 'test-password'));
        self::assertIsString($pkcs12);

        $credentials = SigningCredentials::fromPkcs12($pkcs12, 'test-password');
        $payload = 'Tribux signing credentials smoke test';
        $signature = $credentials->signSha256($payload);

        self::assertSame(
            1,
            openssl_verify($payload, $signature, $credentials->certificate->resource(), OPENSSL_ALGO_SHA256),
        );
        self::assertSame([], $credentials->certificateChain);
    }

    #[Test]
    public function it_rejects_an_invalid_pkcs12_password(): void
    {
        [$key, $certificate] = $this->ephemeralKeyPair();
        $pkcs12 = '';
        self::assertTrue(openssl_pkcs12_export($certificate, $pkcs12, $key, 'correct-password'));
        self::assertIsString($pkcs12);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PKCS#12 contents or password are invalid.');

        SigningCredentials::fromPkcs12($pkcs12, 'wrong-password');
    }

    #[Test]
    public function it_rejects_a_private_key_that_does_not_match_the_certificate(): void
    {
        [$privateKey] = $this->ephemeralKeyPair();
        [, $unrelatedCertificate] = $this->ephemeralKeyPair();
        $privateKeyPem = '';
        $certificatePem = '';
        self::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem, null, $this->opensslOptions()));
        self::assertTrue(openssl_x509_export($unrelatedCertificate, $certificatePem));
        self::assertIsString($privateKeyPem);
        self::assertIsString($certificatePem);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private key does not match');

        SigningCredentials::fromPem($privateKeyPem, $certificatePem);
    }

    #[Test]
    public function it_embeds_the_declared_certificate_path_but_only_the_signer_in_key_info(): void
    {
        [$key, $certificate] = $this->ephemeralKeyPair();
        [, $chainCertificate] = $this->ephemeralKeyPair();
        $privateKeyPem = '';
        $certificatePem = '';
        $chainPem = '';
        self::assertTrue(openssl_pkey_export($key, $privateKeyPem, null, $this->opensslOptions()));
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        self::assertTrue(openssl_x509_export($chainCertificate, $chainPem));
        self::assertIsString($privateKeyPem);
        self::assertIsString($certificatePem);
        self::assertIsString($chainPem);
        $credentials = SigningCredentials::fromPem($privateKeyPem, $certificatePem, [$chainPem]);
        $signer = new Fev19XadesSigner(new FixedSignatureIdGenerator('path', 'key-info'));
        $document = $this->document($signer->sign(
            $this->unsignedInvoice(),
            $credentials,
            DianSignerRole::TechnologyProvider,
            new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1)),
        ));
        $xpath = $this->xpath($document);

        self::assertSame('2', $xpath->evaluate('string(count(//xades:SigningCertificate/xades:Cert))'));
        self::assertSame('1', $xpath->evaluate('string(count(//ds:KeyInfo/ds:X509Data/ds:X509Certificate))'));
        self::assertSame('third party', $xpath->evaluate('string(//xades:ClaimedRole)'));
    }

    #[Test]
    public function it_rejects_a_signing_time_outside_the_certificate_validity(): void
    {
        $credentials = $this->ephemeralCredentials();
        $signer = new Fev19XadesSigner(new FixedSignatureIdGenerator('late', 'key-info'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('outside the signing certificate validity period');

        $signer->sign(
            $this->unsignedInvoice(),
            $credentials,
            DianSignerRole::Supplier,
            new DateTimeImmutable('@'.($credentials->certificate->validTo + 1)),
        );
    }

    #[Test]
    public function it_rejects_an_already_signed_document(): void
    {
        $credentials = $this->ephemeralCredentials();
        $signingTime = new DateTimeImmutable('@'.($credentials->certificate->validFrom + 1));
        $signer = new Fev19XadesSigner(new FixedSignatureIdGenerator('one', 'two'));
        $signed = $signer->sign($this->unsignedInvoice(), $credentials, DianSignerRole::Supplier, $signingTime);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already signed');

        $signer->sign($signed, $credentials, DianSignerRole::Supplier, $signingTime);
    }

    private function unsignedInvoice(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
                     xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
              <ext:UBLExtensions>
                <ext:UBLExtension>
                  <ext:ExtensionContent><Fixture xmlns="urn:tribux:test">unsigned</Fixture></ext:ExtensionContent>
                </ext:UBLExtension>
              </ext:UBLExtensions>
            </Invoice>
            XML;
    }

    private function ephemeralCredentials(): SigningCredentials
    {
        [$key, $certificate] = $this->ephemeralKeyPair();
        $privateKeyPem = '';
        $certificatePem = '';
        self::assertTrue(openssl_pkey_export($key, $privateKeyPem, null, $this->opensslOptions()));
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        self::assertIsString($privateKeyPem);
        self::assertIsString($certificatePem);

        return SigningCredentials::fromPem($privateKeyPem, $certificatePem);
    }

    /** @return array{OpenSSLAsymmetricKey, \OpenSSLCertificate} */
    private function ephemeralKeyPair(): array
    {
        $options = $this->opensslOptions();

        $key = $this->ephemeralPrivateKey($options);

        $requestKey = $key;
        $request = openssl_csr_new([
            'countryName' => 'CO',
            'stateOrProvinceName' => 'Bogota D.C.',
            'localityName' => 'Bogota',
            'organizationName' => 'Tribux Test',
            'organizationalUnitName' => 'Automated Tests',
            'commonName' => 'Ephemeral Tribux Signer',
        ], $requestKey, $options);

        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            throw new \RuntimeException('OpenSSL could not generate an ephemeral certificate request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 2, $options, 314159);

        if (! $certificate instanceof \OpenSSLCertificate) {
            throw new \RuntimeException('OpenSSL could not self-sign an ephemeral certificate.');
        }

        return [$key, $certificate];
    }

    /** @param array<string, int|string> $options */
    private function ephemeralPrivateKey(array $options): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new($options);

        if ($key instanceof OpenSSLAsymmetricKey) {
            return $key;
        }

        throw new \RuntimeException('OpenSSL could not generate an ephemeral RSA key.');
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
        $xpath->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('ext', self::NS_EXT);
        $xpath->registerNamespace('ds', self::NS_DS);
        $xpath->registerNamespace('xades', self::NS_XADES);

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

    private function canonicalize(DOMElement|DOMDocument $node): string
    {
        $canonical = $node->C14N(false, false);
        self::assertIsString($canonical);

        return $canonical;
    }

    private function digestDocumentWithoutSignature(DOMDocument $document, DOMXPath $xpath): string
    {
        $signature = $this->oneElement($xpath, '//ds:Signature');
        $parent = $signature->parentNode;
        self::assertInstanceOf(DOMElement::class, $parent);
        $nextSibling = $signature->nextSibling;
        $parent->removeChild($signature);

        try {
            return $this->sha384($this->canonicalize($document));
        } finally {
            if ($nextSibling !== null) {
                $parent->insertBefore($signature, $nextSibling);
            } else {
                $parent->appendChild($signature);
            }
        }
    }

    private function sha384(string $value): string
    {
        return base64_encode(hash('sha384', $value, true));
    }
}

final class FixedSignatureIdGenerator implements SignatureIdGenerator
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
            throw new \RuntimeException('No test signature ID remains.');
        }

        return $id;
    }
}

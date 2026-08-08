<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates the enveloped XAdES-EPES signature defined by FEV 1.9 section 6.5.10.
 *
 * Profile verified against current v2 examples: inclusive C14N 1.0,
 * RSA-SHA256 signature and SHA-384 digests for all three references.
 */
final readonly class Fev19XadesSigner
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const string NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const string NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const string NS_XADES141 = 'http://uri.etsi.org/01903/v1.4.1#';
    private const string NS_XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const string C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const string ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const string RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const string SIGNED_PROPERTIES_TYPE = 'http://uri.etsi.org/01903#SignedProperties';

    private DianSignaturePolicy $policy;

    public function __construct(
        private SignatureIdGenerator $idGenerator = new RandomSignatureIdGenerator(),
    ) {
        $this->policy = DianSignaturePolicy::version2Sha384();
    }

    public function sign(
        string $unsignedXml,
        SigningCredentials $credentials,
        DianSignerRole $role,
        DateTimeImmutable $signingTime,
    ): string {
        $credentials->assertValidAt($signingTime);
        $document = $this->loadUnsignedDocument($unsignedXml);
        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            throw new InvalidArgumentException('FEV 1.9 XML must have a document element.');
        }

        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:ds', self::NS_DS);
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:xades', self::NS_XADES);
        $root->setAttributeNS(self::NS_XMLNS, 'xmlns:xades141', self::NS_XADES141);

        $extensions = $document->getElementsByTagNameNS(self::NS_EXT, 'UBLExtensions');

        if ($extensions->length !== 1 || ! $extensions->item(0) instanceof DOMElement) {
            throw new InvalidArgumentException('FEV 1.9 XML must contain exactly one ext:UBLExtensions element.');
        }

        $signatureId = 'xmldsig-'.$this->idGenerator->generate();
        $keyInfoId = 'xmldsig-'.$this->idGenerator->generate().'-keyinfo';
        $signedPropertiesId = $signatureId.'-signedprops';
        $extension = $this->append($extensions->item(0), self::NS_EXT, 'ext:UBLExtension');
        $extensionContent = $this->append($extension, self::NS_EXT, 'ext:ExtensionContent');
        $signature = $this->append($extensionContent, self::NS_DS, 'ds:Signature', null, ['Id' => $signatureId]);
        $signedInfo = $this->append($signature, self::NS_DS, 'ds:SignedInfo');
        $this->append($signedInfo, self::NS_DS, 'ds:CanonicalizationMethod', null, ['Algorithm' => self::C14N]);
        $this->append($signedInfo, self::NS_DS, 'ds:SignatureMethod', null, ['Algorithm' => self::RSA_SHA256]);

        $documentDigest = $this->appendReference(
            $signedInfo,
            uri: '',
            id: $signatureId.'-ref0',
            type: null,
            transform: self::ENVELOPED,
        );
        $keyInfoDigest = $this->appendReference($signedInfo, '#'.$keyInfoId);
        $signedPropertiesDigest = $this->appendReference(
            $signedInfo,
            uri: '#'.$signedPropertiesId,
            id: null,
            type: self::SIGNED_PROPERTIES_TYPE,
        );
        $signatureValue = $this->append(
            $signature,
            self::NS_DS,
            'ds:SignatureValue',
            '',
            ['Id' => $signatureId.'-sigvalue'],
        );
        $keyInfo = $this->append($signature, self::NS_DS, 'ds:KeyInfo', null, ['Id' => $keyInfoId]);
        $x509Data = $this->append($keyInfo, self::NS_DS, 'ds:X509Data');
        $this->append($x509Data, self::NS_DS, 'ds:X509Certificate', $credentials->certificate->base64);
        $object = $this->append($signature, self::NS_DS, 'ds:Object');
        $qualifyingProperties = $this->append(
            $object,
            self::NS_XADES,
            'xades:QualifyingProperties',
            null,
            ['Target' => '#'.$signatureId],
        );
        $signedProperties = $this->append(
            $qualifyingProperties,
            self::NS_XADES,
            'xades:SignedProperties',
            null,
            ['Id' => $signedPropertiesId],
        );
        $signedSignatureProperties = $this->append(
            $signedProperties,
            self::NS_XADES,
            'xades:SignedSignatureProperties',
        );
        $this->append(
            $signedSignatureProperties,
            self::NS_XADES,
            'xades:SigningTime',
            $signingTime->format('Y-m-d\TH:i:s.vP'),
        );
        $this->appendSigningCertificates($signedSignatureProperties, $credentials);
        $this->appendSignaturePolicy($signedSignatureProperties);
        $this->appendSignerRole($signedSignatureProperties, $role);

        $documentDigest->nodeValue = $this->digestDocumentWithoutSignature($document, $signature);
        $keyInfoDigest->nodeValue = $this->sha384($this->canonicalize($keyInfo));
        $signedPropertiesDigest->nodeValue = $this->sha384($this->canonicalize($signedProperties));
        $signatureValue->nodeValue = base64_encode($credentials->signSha256($this->canonicalize($signedInfo)));
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Could not serialize the signed FEV 1.9 XML.');
        }

        return $xml;
    }

    private function loadUnsignedDocument(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $document->formatOutput = false;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new InvalidArgumentException('Unsigned FEV 1.9 input must be well-formed XML without a DOCTYPE.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document->getElementsByTagNameNS(self::NS_DS, 'Signature')->length !== 0) {
            throw new InvalidArgumentException('FEV 1.9 input is already signed.');
        }

        return $document;
    }

    private function appendReference(
        DOMElement $signedInfo,
        string $uri,
        ?string $id = null,
        ?string $type = null,
        ?string $transform = null,
    ): DOMElement {
        $attributes = ['URI' => $uri];

        if ($id !== null) {
            $attributes = ['Id' => $id, ...$attributes];
        }

        if ($type !== null) {
            $attributes['Type'] = $type;
        }

        $reference = $this->append($signedInfo, self::NS_DS, 'ds:Reference', null, $attributes);

        if ($transform !== null) {
            $transforms = $this->append($reference, self::NS_DS, 'ds:Transforms');
            $this->append($transforms, self::NS_DS, 'ds:Transform', null, ['Algorithm' => $transform]);
        }

        $this->append($reference, self::NS_DS, 'ds:DigestMethod', null, [
            'Algorithm' => $this->policy->digestMethod,
        ]);

        return $this->append($reference, self::NS_DS, 'ds:DigestValue', '');
    }

    private function appendSigningCertificates(
        DOMElement $signedSignatureProperties,
        SigningCredentials $credentials,
    ): void {
        $signingCertificate = $this->append(
            $signedSignatureProperties,
            self::NS_XADES,
            'xades:SigningCertificate',
        );

        foreach ($credentials->signingCertificatePath() as $certificate) {
            $cert = $this->append($signingCertificate, self::NS_XADES, 'xades:Cert');
            $certDigest = $this->append($cert, self::NS_XADES, 'xades:CertDigest');
            $this->append($certDigest, self::NS_DS, 'ds:DigestMethod', null, [
                'Algorithm' => $this->policy->digestMethod,
            ]);
            $this->append($certDigest, self::NS_DS, 'ds:DigestValue', $this->sha384($certificate->der));
            $issuerSerial = $this->append($cert, self::NS_XADES, 'xades:IssuerSerial');
            $this->append($issuerSerial, self::NS_DS, 'ds:X509IssuerName', $certificate->issuerName);
            $this->append($issuerSerial, self::NS_DS, 'ds:X509SerialNumber', $certificate->serialNumber);
        }
    }

    private function appendSignaturePolicy(DOMElement $signedSignatureProperties): void
    {
        $identifier = $this->append(
            $signedSignatureProperties,
            self::NS_XADES,
            'xades:SignaturePolicyIdentifier',
        );
        $policyId = $this->append($identifier, self::NS_XADES, 'xades:SignaturePolicyId');
        $sigPolicyId = $this->append($policyId, self::NS_XADES, 'xades:SigPolicyId');
        $this->append($sigPolicyId, self::NS_XADES, 'xades:Identifier', $this->policy->identifier);
        $policyHash = $this->append($policyId, self::NS_XADES, 'xades:SigPolicyHash');
        $this->append($policyHash, self::NS_DS, 'ds:DigestMethod', null, [
            'Algorithm' => $this->policy->digestMethod,
        ]);
        $this->append($policyHash, self::NS_DS, 'ds:DigestValue', $this->policy->digestValue);
    }

    private function appendSignerRole(DOMElement $signedSignatureProperties, DianSignerRole $role): void
    {
        $signerRole = $this->append($signedSignatureProperties, self::NS_XADES, 'xades:SignerRole');
        $claimedRoles = $this->append($signerRole, self::NS_XADES, 'xades:ClaimedRoles');
        $this->append($claimedRoles, self::NS_XADES, 'xades:ClaimedRole', $role->value);
    }

    private function digestDocumentWithoutSignature(DOMDocument $document, DOMElement $signature): string
    {
        $parent = $signature->parentNode;

        if (! $parent instanceof DOMElement) {
            throw new RuntimeException('Signature must have an extension content parent.');
        }

        $nextSibling = $signature->nextSibling;
        $parent->removeChild($signature);

        try {
            return $this->sha384($this->canonicalize($document));
        } finally {
            if ($nextSibling instanceof DOMNode) {
                $parent->insertBefore($signature, $nextSibling);
            } else {
                $parent->appendChild($signature);
            }
        }
    }

    private function canonicalize(DOMNode $node): string
    {
        $canonical = $node->C14N(false, false);

        if ($canonical === false) {
            throw new RuntimeException('Could not canonicalize an XML signature node.');
        }

        return $canonical;
    }

    private function sha384(string $value): string
    {
        return base64_encode(hash('sha384', $value, true));
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
            throw new RuntimeException('Cannot append a signature element without an owner document.');
        }

        $element = $document->createElementNS($namespace, $qualifiedName);

        if ($value !== null) {
            $element->appendChild($document->createTextNode($value));
        }

        foreach ($attributes as $name => $attributeValue) {
            $element->setAttribute($name, $attributeValue);
        }

        $parent->appendChild($element);

        return $element;
    }
}

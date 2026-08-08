<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use LibXMLError;
use Tribux\Dian\Soap\Transport\DianSoapHttpResponse;
use Tribux\Dian\Validation\XmlValidationError;

final class DianSoapResponseParser
{
    private const string NS_SOAP = 'http://www.w3.org/2003/05/soap-envelope';
    private const string NS_WCF = 'http://wcf.dian.colombia';
    private const string NS_ARRAYS = 'http://schemas.microsoft.com/2003/10/Serialization/Arrays';
    private const string NS_DIAN_RESPONSE = 'http://schemas.datacontract.org/2004/07/DianResponse';
    private const string NS_UPLOAD = 'http://schemas.datacontract.org/2004/07/UploadDocumentResponse';
    private const string NS_TRACK = 'http://schemas.datacontract.org/2004/07/XmlParamsResponseTrackId';
    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const string NS_XML = 'http://www.w3.org/XML/1998/namespace';

    public function parseSendTestSetAsync(DianSoapHttpResponse $response): SendTestSetAsyncResponse
    {
        [$xpath, $body] = $this->responseContext($response);
        $fault = $this->faultFromBody($xpath, $body, $response);

        if ($fault !== null) {
            return new SendTestSetAsyncResponse(
                httpStatusCode: $response->statusCode,
                rawXml: $response->body,
                zipKey: null,
                messages: [],
                fault: $fault,
            );
        }

        $result = $this->oneElement(
            $xpath,
            './wcf:SendTestSetAsyncResponse/wcf:SendTestSetAsyncResult',
            $response,
            'SendTestSetAsyncResult is missing or duplicated.',
            $body,
        );
        $messages = [];
        $messageNodes = $xpath->query('./upload:ErrorMessageList/track:XmlParamsResponseTrackId', $result);

        if ($messageNodes === false) {
            throw new DianSoapProtocolException('Could not query UploadDocumentResponse messages.', $response);
        }

        foreach ($messageNodes as $messageNode) {
            if (! $messageNode instanceof DOMElement) {
                continue;
            }

            $messages[] = new UploadDocumentMessage(
                documentKey: $this->nullableText($xpath, './track:DocumentKey', $messageNode, $response),
                processedMessage: $this->nullableText($xpath, './track:ProcessedMessage', $messageNode, $response),
                senderCode: $this->nullableText($xpath, './track:SenderCode', $messageNode, $response),
                success: $this->nullableBoolean($xpath, './track:Success', $messageNode, $response),
                xmlFileName: $this->nullableText($xpath, './track:XmlFileName', $messageNode, $response),
            );
        }

        return new SendTestSetAsyncResponse(
            httpStatusCode: $response->statusCode,
            rawXml: $response->body,
            zipKey: $this->nullableText($xpath, './upload:ZipKey', $result, $response),
            messages: $messages,
            fault: null,
        );
    }

    public function parseGetStatus(DianSoapHttpResponse $response): GetStatusResponse
    {
        [$xpath, $body] = $this->responseContext($response);
        $fault = $this->faultFromBody($xpath, $body, $response);

        if ($fault !== null) {
            return new GetStatusResponse(
                httpStatusCode: $response->statusCode,
                rawXml: $response->body,
                result: null,
                fault: $fault,
            );
        }

        $result = $this->oneElement(
            $xpath,
            './wcf:GetStatusResponse/wcf:GetStatusResult',
            $response,
            'GetStatusResult is missing or duplicated.',
            $body,
        );

        if ($this->isNil($result)) {
            return new GetStatusResponse($response->statusCode, $response->body, null, null);
        }

        return new GetStatusResponse(
            httpStatusCode: $response->statusCode,
            rawXml: $response->body,
            result: $this->parseDianResponse($xpath, $result, $response),
            fault: null,
        );
    }

    public function parseGetStatusZip(DianSoapHttpResponse $response): GetStatusZipResponse
    {
        [$xpath, $body] = $this->responseContext($response);
        $fault = $this->faultFromBody($xpath, $body, $response);

        if ($fault !== null) {
            return new GetStatusZipResponse(
                httpStatusCode: $response->statusCode,
                rawXml: $response->body,
                results: null,
                fault: $fault,
            );
        }

        $result = $this->oneElement(
            $xpath,
            './wcf:GetStatusZipResponse/wcf:GetStatusZipResult',
            $response,
            'GetStatusZipResult is missing or duplicated.',
            $body,
        );

        if ($this->isNil($result)) {
            return new GetStatusZipResponse($response->statusCode, $response->body, null, null);
        }

        $responseNodes = $xpath->query('./dian:DianResponse', $result);

        if ($responseNodes === false) {
            throw new DianSoapProtocolException('Could not query GetStatusZip DianResponse entries.', $response);
        }

        $results = [];

        foreach ($responseNodes as $responseNode) {
            if ($responseNode instanceof DOMElement) {
                $results[] = $this->isNil($responseNode)
                    ? null
                    : $this->parseDianResponse($xpath, $responseNode, $response);
            }
        }

        return new GetStatusZipResponse(
            httpStatusCode: $response->statusCode,
            rawXml: $response->body,
            results: $results,
            fault: null,
        );
    }

    private function parseDianResponse(
        DOMXPath $xpath,
        DOMElement $element,
        DianSoapHttpResponse $response,
    ): DianResponse {
        $errorMessages = [];
        $errorNodes = $xpath->query('./dian:ErrorMessage/arrays:string', $element);

        if ($errorNodes === false) {
            throw new DianSoapProtocolException('Could not query DianResponse error messages.', $response);
        }

        foreach ($errorNodes as $errorNode) {
            if ($errorNode instanceof DOMElement) {
                $errorMessages[] = $this->isNil($errorNode) ? null : $errorNode->textContent;
            }
        }

        return new DianResponse(
            errorMessages: $errorMessages,
            isValid: $this->nullableBoolean($xpath, './dian:IsValid', $element, $response),
            statusCode: $this->nullableText($xpath, './dian:StatusCode', $element, $response),
            statusDescription: $this->nullableText($xpath, './dian:StatusDescription', $element, $response),
            statusMessage: $this->nullableText($xpath, './dian:StatusMessage', $element, $response),
            xmlBase64Bytes: $this->nullableBase64($xpath, './dian:XmlBase64Bytes', $element, $response),
            xmlBytes: $this->nullableBase64($xpath, './dian:XmlBytes', $element, $response),
            xmlDocumentKey: $this->nullableText($xpath, './dian:XmlDocumentKey', $element, $response),
            xmlFileName: $this->nullableText($xpath, './dian:XmlFileName', $element, $response),
        );
    }

    /** @return array{DOMXPath, DOMElement} */
    private function responseContext(DianSoapHttpResponse $response): array
    {
        $document = $this->loadDocument($response);
        $xpath = new DOMXPath($document);

        foreach ([
            'arrays' => self::NS_ARRAYS,
            'dian' => self::NS_DIAN_RESPONSE,
            's' => self::NS_SOAP,
            'track' => self::NS_TRACK,
            'upload' => self::NS_UPLOAD,
            'wcf' => self::NS_WCF,
            'xsi' => self::NS_XSI,
        ] as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        $body = $this->oneElement($xpath, '/s:Envelope/s:Body', $response, 'SOAP 1.2 Body is missing or duplicated.');
        $bodyChildren = $xpath->query('./*', $body);

        if ($bodyChildren === false || $bodyChildren->length !== 1) {
            throw new DianSoapProtocolException('SOAP Body must contain exactly one response or Fault.', $response);
        }

        return [$xpath, $body];
    }

    private function faultFromBody(
        DOMXPath $xpath,
        DOMElement $body,
        DianSoapHttpResponse $response,
    ): ?DianSoapFault {
        $faults = $xpath->query('./s:Fault', $body);

        if ($faults === false || $faults->length > 1) {
            throw new DianSoapProtocolException('SOAP Fault is duplicated or unreadable.', $response);
        }

        $fault = $faults->item(0);

        return $fault instanceof DOMElement ? $this->parseFault($xpath, $fault, $response) : null;
    }

    private function loadDocument(DianSoapHttpResponse $response): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($response->body, LIBXML_NONET | LIBXML_COMPACT);
            $errors = array_map(
                static fn (LibXMLError $error): XmlValidationError => XmlValidationError::fromLibxml($error),
                libxml_get_errors(),
            );

            if (! $loaded || $document->doctype !== null) {
                throw new DianSoapProtocolException(
                    'Response is not well-formed XML without a DOCTYPE.',
                    $response,
                    $errors,
                );
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function parseFault(
        DOMXPath $xpath,
        DOMElement $fault,
        DianSoapHttpResponse $response,
    ): DianSoapFault {
        $code = $this->requiredText($xpath, './s:Code/s:Value', $fault, $response);
        $subcode = $this->nullableText($xpath, './s:Code/s:Subcode/s:Value', $fault, $response);
        $reasonNodes = $xpath->query('./s:Reason/s:Text', $fault);

        if ($reasonNodes === false || $reasonNodes->length === 0) {
            throw new DianSoapProtocolException('SOAP Fault must contain at least one Reason Text.', $response);
        }

        $reasons = [];

        foreach ($reasonNodes as $reasonNode) {
            if (! $reasonNode instanceof DOMElement) {
                continue;
            }

            $language = $reasonNode->getAttributeNS(self::NS_XML, 'lang');
            $reasons[] = [
                'language' => $language === '' ? null : $language,
                'text' => $reasonNode->textContent,
            ];
        }

        if ($reasons === []) {
            throw new DianSoapProtocolException('SOAP Fault contains no readable Reason Text.', $response);
        }

        $detail = $xpath->query('./s:Detail', $fault);

        if ($detail === false || $detail->length > 1) {
            throw new DianSoapProtocolException('SOAP Fault Detail is duplicated or unreadable.', $response);
        }

        $detailXml = null;

        if ($detail->length === 1 && $detail->item(0) instanceof DOMElement) {
            $detailXml = $fault->ownerDocument?->saveXML($detail->item(0));

            if (! is_string($detailXml)) {
                throw new DianSoapProtocolException('Could not serialize SOAP Fault Detail.', $response);
            }
        }

        return new DianSoapFault($code, $subcode, $reasons, $detailXml);
    }

    private function nullableBoolean(
        DOMXPath $xpath,
        string $query,
        DOMNode $context,
        DianSoapHttpResponse $response,
    ): ?bool {
        $value = $this->nullableText($xpath, $query, $context, $response);

        if ($value === null) {
            return null;
        }

        return match ($value) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => throw new DianSoapProtocolException('SOAP boolean has an invalid lexical value.', $response),
        };
    }

    private function nullableBase64(
        DOMXPath $xpath,
        string $query,
        DOMNode $context,
        DianSoapHttpResponse $response,
    ): ?DianBase64Value {
        $encoded = $this->nullableText($xpath, $query, $context, $response);

        if ($encoded === null) {
            return null;
        }

        $compact = preg_replace('/\s+/', '', $encoded);
        $decoded = is_string($compact) ? base64_decode($compact, true) : false;

        if (! is_string($decoded)) {
            throw new DianSoapProtocolException('SOAP base64Binary has an invalid lexical value: '.$query, $response);
        }

        return new DianBase64Value($encoded, $decoded);
    }

    private function requiredText(
        DOMXPath $xpath,
        string $query,
        DOMNode $context,
        DianSoapHttpResponse $response,
    ): string {
        $value = $this->nullableText($xpath, $query, $context, $response);

        if ($value === null) {
            throw new DianSoapProtocolException('Required SOAP value is missing or nil: '.$query, $response);
        }

        return $value;
    }

    private function nullableText(
        DOMXPath $xpath,
        string $query,
        DOMNode $context,
        DianSoapHttpResponse $response,
    ): ?string {
        $nodes = $xpath->query($query, $context);

        if ($nodes === false || $nodes->length > 1) {
            throw new DianSoapProtocolException('SOAP value is duplicated or unreadable: '.$query, $response);
        }

        if ($nodes->length === 0 || ! $nodes->item(0) instanceof DOMElement) {
            return null;
        }

        $element = $nodes->item(0);

        if ($this->isNil($element)) {
            return null;
        }

        return $element->textContent;
    }

    private function isNil(DOMElement $element): bool
    {
        return in_array($element->getAttributeNS(self::NS_XSI, 'nil'), ['1', 'true'], true);
    }

    private function oneElement(
        DOMXPath $xpath,
        string $query,
        DianSoapHttpResponse $response,
        string $reason,
        ?DOMNode $context = null,
    ): DOMElement {
        $nodes = $xpath->query($query, $context);

        if ($nodes === false || $nodes->length !== 1 || ! $nodes->item(0) instanceof DOMElement) {
            throw new DianSoapProtocolException($reason, $response);
        }

        return $nodes->item(0);
    }
}

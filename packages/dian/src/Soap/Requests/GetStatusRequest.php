<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Requests;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;
use Tribux\Dian\Soap\DianSoapBody;
use Tribux\Dian\Soap\DianSoapOperation;

final readonly class GetStatusRequest implements DianSoapBody
{
    private const string NS_WCF = 'http://wcf.dian.colombia';

    public function __construct(public string $trackId)
    {
        if (trim($trackId) === '') {
            throw new InvalidArgumentException('GetStatus trackId cannot be empty.');
        }
    }

    public function operation(): DianSoapOperation
    {
        return DianSoapOperation::GetStatus;
    }

    public function appendTo(DOMElement $soapBody): void
    {
        $document = $soapBody->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('SOAP body must have an owner document.');
        }

        $operation = $document->createElementNS(self::NS_WCF, 'wcf:GetStatus');
        $trackId = $document->createElementNS(self::NS_WCF, 'wcf:trackId');
        $trackId->appendChild($document->createTextNode($this->trackId));
        $operation->appendChild($trackId);
        $soapBody->appendChild($operation);
    }
}

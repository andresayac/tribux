<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Requests;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;
use Tribux\Dian\Soap\DianSoapBody;
use Tribux\Dian\Soap\DianSoapOperation;

final readonly class SendTestSetAsyncRequest implements DianSoapBody
{
    private const string NS_WCF = 'http://wcf.dian.colombia';

    public function __construct(
        public string $fileName,
        #[SensitiveParameter]
        public string $zipContents,
        public string $testSetId,
    ) {
        if (trim($fileName) === '') {
            throw new InvalidArgumentException('SendTestSetAsync fileName cannot be empty.');
        }

        if ($zipContents === '') {
            throw new InvalidArgumentException('SendTestSetAsync ZIP contents cannot be empty.');
        }

        if (trim($testSetId) === '') {
            throw new InvalidArgumentException('SendTestSetAsync testSetId cannot be empty.');
        }
    }

    public function operation(): DianSoapOperation
    {
        return DianSoapOperation::SendTestSetAsync;
    }

    public function appendTo(DOMElement $soapBody): void
    {
        $document = $soapBody->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('SOAP body must have an owner document.');
        }

        $operation = $document->createElementNS(self::NS_WCF, 'wcf:SendTestSetAsync');
        $this->appendText($operation, 'wcf:fileName', $this->fileName);
        $this->appendText($operation, 'wcf:contentFile', base64_encode($this->zipContents));
        $this->appendText($operation, 'wcf:testSetId', $this->testSetId);
        $soapBody->appendChild($operation);
    }

    private function appendText(DOMElement $parent, string $qualifiedName, string $value): void
    {
        $document = $parent->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('SOAP operation must have an owner document.');
        }

        $element = $document->createElementNS(self::NS_WCF, $qualifiedName);
        $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);
    }
}

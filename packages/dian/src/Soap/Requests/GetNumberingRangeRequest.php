<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Requests;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;
use Tribux\Dian\Soap\DianSoapBody;
use Tribux\Dian\Soap\DianSoapOperation;

/**
 * Document/literal body of GetNumberingRange.
 *
 * The WSDL declares accountCode, accountCodeT and softwareCode as optional and
 * nillable strings, in that order, so a null is sent as an explicit xsi:nil
 * element rather than omitted or blanked. Tribux does not guess which
 * combination the service requires; it only refuses an empty string, which is
 * neither a value nor a null.
 */
final readonly class GetNumberingRangeRequest implements DianSoapBody
{
    private const string NS_WCF = 'http://wcf.dian.colombia';

    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        public ?string $accountCode = null,
        public ?string $accountCodeT = null,
        public ?string $softwareCode = null,
    ) {
        foreach ([
            'accountCode' => $accountCode,
            'accountCodeT' => $accountCodeT,
            'softwareCode' => $softwareCode,
        ] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf(
                    'GetNumberingRange %s must be a non-empty value or null.',
                    $field,
                ));
            }
        }
    }

    public function operation(): DianSoapOperation
    {
        return DianSoapOperation::GetNumberingRange;
    }

    public function appendTo(DOMElement $soapBody): void
    {
        $document = $soapBody->ownerDocument;

        if (! $document instanceof DOMDocument) {
            throw new RuntimeException('SOAP body must have an owner document.');
        }

        $operation = $document->createElementNS(self::NS_WCF, 'wcf:GetNumberingRange');

        foreach ([
            'accountCode' => $this->accountCode,
            'accountCodeT' => $this->accountCodeT,
            'softwareCode' => $this->softwareCode,
        ] as $field => $value) {
            $element = $document->createElementNS(self::NS_WCF, 'wcf:'.$field);

            if ($value === null) {
                $element->setAttributeNS(self::NS_XSI, 'xsi:nil', 'true');
            } else {
                $element->appendChild($document->createTextNode($value));
            }

            $operation->appendChild($element);
        }

        $soapBody->appendChild($operation);
    }
}

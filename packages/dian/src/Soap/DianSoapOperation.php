<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

/**
 * Initial FEV operations observed in both official single-file WSDLs.
 *
 * Presence in a WSDL does not by itself establish business-level availability.
 */
enum DianSoapOperation: string
{
    case GetNumberingRange = 'GetNumberingRange';
    case GetStatus = 'GetStatus';
    case GetStatusZip = 'GetStatusZip';
    case SendBillSync = 'SendBillSync';
    case SendTestSetAsync = 'SendTestSetAsync';

    public function action(): string
    {
        return 'http://wcf.dian.colombia/IWcfDianCustomerServices/'.$this->value;
    }
}

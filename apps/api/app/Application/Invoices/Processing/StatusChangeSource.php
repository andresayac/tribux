<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

/**
 * Who caused a status change. Keeping DIAN separate from the worker is what
 * lets the audit trail show whether Tribux decided a state or DIAN reported it.
 */
enum StatusChangeSource: string
{
    case Api = 'api';
    case Worker = 'worker';
    case Dian = 'dian';
    case Operator = 'operator';
}

<?php

declare(strict_types=1);

namespace App\Application\Invoices\Numbering;

/**
 * FEV 1.9 names the XML and the ZIP with their own eight-character sequence.
 * They are counted separately because a package does not always contain
 * exactly one document.
 */
enum DocumentSequenceScope: string
{
    case Xml = 'xml';
    case Zip = 'zip';
}

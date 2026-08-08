<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

/**
 * The live WSDL requires ThumbprintSHA1; the historical DIAN guide shows a
 * BinarySecurityToken reference. Both remain explicit until habilitation proves
 * which profile the remote validator accepts.
 */
enum SoapCertificateReference
{
    case ThumbprintSha1;
    case BinarySecurityToken;
}

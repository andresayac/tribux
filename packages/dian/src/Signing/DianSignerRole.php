<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

/** Source: DIAN signature policy v2, section 12. */
enum DianSignerRole: string
{
    case Supplier = 'supplier';
    case TechnologyProvider = 'third party';
}

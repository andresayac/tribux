<?php

declare(strict_types=1);

namespace Tribux\Core\Decimal;

enum DecimalRoundingMode: string
{
    /** Discard fractional digits beyond the requested scale (toward zero). */
    case Down = 'down';

    /** Round to nearest; a discarded 5 rounds away from zero. */
    case HalfUp = 'half_up';
}

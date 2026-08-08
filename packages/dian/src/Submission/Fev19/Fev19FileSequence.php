<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

use InvalidArgumentException;

/**
 * Exact eight-character token required by FEV 1.9 sections 6.5.7 and 6.5.8.
 *
 * This value deliberately does not increment or convert an ordinal. Q-008
 * tracks the contradiction between the hexadecimal rule and decimal-looking
 * examples in the official annex.
 */
final readonly class Fev19FileSequence
{
    public function __construct(public string $encoded)
    {
        if (preg_match('/\A[0-9A-F]{8}\z/D', $encoded) !== 1 || $encoded === '00000000') {
            throw new InvalidArgumentException(
                'FEV 1.9 file sequence must be an uppercase hexadecimal token from 00000001 to FFFFFFFF.',
            );
        }
    }
}

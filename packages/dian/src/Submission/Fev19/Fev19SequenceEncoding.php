<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

use InvalidArgumentException;

/**
 * How an ordinal becomes the eight-character file sequence token.
 *
 * The annex calls the token hexadecimal, while its own example for the eleventh
 * submission shows `00000011` rather than `0000000B`. Both readings agree up to
 * nine and diverge from ten onwards, so Tribux refuses to guess: there is no
 * default, the issuer configuration must state which encoding it uses, and the
 * choice is recorded with the submission. Q-008 tracks the contradiction.
 */
enum Fev19SequenceEncoding: string
{
    /** Matches the literal example in sections 6.5.7 and 6.5.8. */
    case Decimal = 'decimal';

    /** Matches the prose that calls the token hexadecimal. */
    case Hexadecimal = 'hexadecimal';

    public function maximumOrdinal(): int
    {
        return match ($this) {
            self::Decimal => 99999999,
            self::Hexadecimal => 0xFFFFFFFF,
        };
    }

    public function encode(int $ordinal): Fev19FileSequence
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException('A FEV 1.9 file sequence ordinal starts at one.');
        }

        if ($ordinal > $this->maximumOrdinal()) {
            throw new InvalidArgumentException(sprintf(
                'Ordinal %d does not fit an eight-character %s sequence; the annual sequence must roll over first.',
                $ordinal,
                $this->value,
            ));
        }

        return new Fev19FileSequence(match ($this) {
            self::Decimal => str_pad((string) $ordinal, 8, '0', STR_PAD_LEFT),
            self::Hexadecimal => strtoupper(str_pad(dechex($ordinal), 8, '0', STR_PAD_LEFT)),
        });
    }
}

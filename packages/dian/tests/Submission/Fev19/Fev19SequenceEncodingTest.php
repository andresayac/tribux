<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Submission\Fev19;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Submission\Fev19\Fev19SequenceEncoding;

final class Fev19SequenceEncodingTest extends TestCase
{
    #[Test]
    public function both_readings_agree_below_ten(): void
    {
        for ($ordinal = 1; $ordinal <= 9; $ordinal++) {
            self::assertSame(
                Fev19SequenceEncoding::Decimal->encode($ordinal)->encoded,
                Fev19SequenceEncoding::Hexadecimal->encode($ordinal)->encoded,
                sprintf('Ordinal %d must be unambiguous.', $ordinal),
            );
        }
    }

    #[Test]
    public function the_readings_diverge_from_ten_onwards(): void
    {
        self::assertSame('00000010', Fev19SequenceEncoding::Decimal->encode(10)->encoded);
        self::assertSame('0000000A', Fev19SequenceEncoding::Hexadecimal->encode(10)->encoded);
    }

    #[Test]
    public function decimal_reproduces_the_literal_example_of_the_eleventh_submission(): void
    {
        // Sections 6.5.7 and 6.5.8 print 00000011 for submission eleven, while
        // the surrounding prose calls the token hexadecimal. See Q-008.
        self::assertSame('00000011', Fev19SequenceEncoding::Decimal->encode(11)->encoded);
        self::assertSame('0000000B', Fev19SequenceEncoding::Hexadecimal->encode(11)->encoded);
    }

    #[Test]
    public function it_rejects_an_ordinal_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('starts at one');

        Fev19SequenceEncoding::Decimal->encode(0);
    }

    #[Test]
    public function each_reading_stops_at_its_own_ceiling(): void
    {
        self::assertSame('99999999', Fev19SequenceEncoding::Decimal->encode(99999999)->encoded);
        self::assertSame('FFFFFFFF', Fev19SequenceEncoding::Hexadecimal->encode(0xFFFFFFFF)->encoded);

        foreach ([
            [Fev19SequenceEncoding::Decimal, 100000000],
            [Fev19SequenceEncoding::Hexadecimal, 0x100000000],
        ] as [$encoding, $overflow]) {
            try {
                $encoding->encode($overflow);
                self::fail('An ordinal beyond eight characters must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('does not fit an eight-character', $exception->getMessage());
            }
        }
    }
}

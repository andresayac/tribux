<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Numbering;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Numbering\AuthorizationNotActive;
use Tribux\Core\Numbering\NumberingAuthorization;
use Tribux\Core\Numbering\NumberOutsideAuthorizedRange;
use Tribux\Core\Numbering\ReservedNumber;

final class NumberingAuthorizationTest extends TestCase
{
    #[Test]
    public function it_describes_the_granted_range(): void
    {
        $authorization = $this->authorization();

        self::assertSame(5000000, $authorization->capacity());
        self::assertTrue($authorization->covers(1));
        self::assertTrue($authorization->covers(5000000));
        self::assertFalse($authorization->covers(0));
        self::assertFalse($authorization->covers(5000001));
        self::assertSame('SETP42', $authorization->format(42));
    }

    #[Test]
    public function validity_is_inclusive_on_both_boundary_days(): void
    {
        $authorization = $this->authorization();

        self::assertTrue($authorization->isActiveOn(new DateTimeImmutable('2026-01-01T00:00:00-05:00')));
        self::assertTrue($authorization->isActiveOn(new DateTimeImmutable('2027-12-31T23:59:59-05:00')));
        self::assertFalse($authorization->isActiveOn(new DateTimeImmutable('2025-12-31T23:59:59-05:00')));
        self::assertFalse($authorization->isActiveOn(new DateTimeImmutable('2028-01-01T00:00:00-05:00')));
    }

    #[Test]
    public function validity_is_read_in_the_moment_the_caller_supplies(): void
    {
        $authorization = $this->authorization();

        // The same instant is the last valid day in Bogotá and the first
        // invalid one in UTC, which is why the caller must localise it.
        $lastLocalDay = new DateTimeImmutable('2027-12-31T20:00:00-05:00');

        self::assertTrue($authorization->isActiveOn($lastLocalDay));
        self::assertFalse($authorization->isActiveOn($lastLocalDay->setTimezone(new DateTimeZone('UTC'))));
    }

    #[Test]
    public function it_refuses_a_number_outside_the_range(): void
    {
        $this->expectException(NumberOutsideAuthorizedRange::class);
        $this->expectExceptionMessage('outside the range 1-5000000');

        $this->authorization()->assertCanIssue(5000001, new DateTimeImmutable('2026-08-08T10:00:00-05:00'));
    }

    #[Test]
    public function it_refuses_to_issue_outside_the_validity_period(): void
    {
        $this->expectException(AuthorizationNotActive::class);
        $this->expectExceptionMessage('only valid from 2026-01-01 to 2027-12-31');

        $this->authorization()->assertCanIssue(1, new DateTimeImmutable('2028-01-02T10:00:00-05:00'));
    }

    #[Test]
    public function a_reserved_number_keeps_both_the_ordinal_and_the_document_value(): void
    {
        $reserved = ReservedNumber::from($this->authorization(), 11);

        self::assertSame('18760000001', $reserved->authorizationReference);
        self::assertSame('SETP', $reserved->prefix);
        self::assertSame(11, $reserved->ordinal);
        self::assertSame('SETP11', $reserved->value);
    }

    /** @return iterable<string, array{string, string, int, int, string, string, string}> */
    public static function invalidAuthorizations(): iterable
    {
        yield 'empty reference' => ['', 'SETP', 1, 10, '2026-01-01', '2026-12-31', 'reference cannot be empty'];
        yield 'prefix with whitespace' => ['R1', 'SE TP', 1, 10, '2026-01-01', '2026-12-31', 'cannot contain whitespace'];
        yield 'range starting at zero' => ['R1', 'SETP', 0, 10, '2026-01-01', '2026-12-31', 'must start at one'];
        yield 'inverted range' => ['R1', 'SETP', 10, 1, '2026-01-01', '2026-12-31', 'cannot end before it starts'];
        yield 'inverted validity' => ['R1', 'SETP', 1, 10, '2026-12-31', '2026-01-01', 'cannot expire before'];
    }

    #[Test]
    #[DataProvider('invalidAuthorizations')]
    public function it_rejects_an_unusable_grant(
        string $reference,
        string $prefix,
        int $from,
        int $to,
        string $validFrom,
        string $validTo,
        string $expectedMessage,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new NumberingAuthorization(
            $reference,
            $prefix,
            $from,
            $to,
            new DateTimeImmutable($validFrom),
            new DateTimeImmutable($validTo),
        );
    }

    private function authorization(): NumberingAuthorization
    {
        return new NumberingAuthorization(
            reference: '18760000001',
            prefix: 'SETP',
            from: 1,
            to: 5000000,
            validFrom: new DateTimeImmutable('2026-01-01'),
            validTo: new DateTimeImmutable('2027-12-31'),
        );
    }
}

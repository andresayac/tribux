<?php

declare(strict_types=1);

namespace Tribux\Core\Numbering;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A range of document numbers an authority granted to an issuer.
 *
 * Framework-independent and country-independent on purpose: the DIAN
 * resolution, its authorization number and its codes live in the DIAN layer,
 * and only the shape of the grant — prefix, bounds and validity — belongs here.
 */
final readonly class NumberingAuthorization
{
    public function __construct(
        public string $reference,
        public string $prefix,
        public int $from,
        public int $to,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
    ) {
        if (trim($reference) === '') {
            throw new InvalidArgumentException('Numbering authorization reference cannot be empty.');
        }

        if ($prefix !== trim($prefix) || preg_match('/\s/', $prefix) === 1) {
            throw new InvalidArgumentException('Numbering authorization prefix cannot contain whitespace.');
        }

        if ($from < 1) {
            throw new InvalidArgumentException('Numbering authorization must start at one or above.');
        }

        if ($to < $from) {
            throw new InvalidArgumentException('Numbering authorization cannot end before it starts.');
        }

        if ($validTo < $validFrom) {
            throw new InvalidArgumentException('Numbering authorization cannot expire before it becomes valid.');
        }
    }

    public function covers(int $number): bool
    {
        return $number >= $this->from && $number <= $this->to;
    }

    /**
     * Validity is granted per calendar day, so the caller must pass a moment
     * already expressed in the issuer's time zone. Comparing an arbitrary
     * instant would silently shift the first and last valid day.
     */
    public function isActiveOn(DateTimeImmutable $issuerLocalMoment): bool
    {
        $day = $issuerLocalMoment->format('Y-m-d');

        return $day >= $this->validFrom->format('Y-m-d') && $day <= $this->validTo->format('Y-m-d');
    }

    public function capacity(): int
    {
        return $this->to - $this->from + 1;
    }

    public function format(int $number): string
    {
        return $this->prefix.$number;
    }

    /**
     * The ordinal behind a formatted value, or null when the value does not
     * belong to this authorization at all.
     *
     * Used to check a client-supplied number instead of reserving one. A
     * leading zero is rejected: "SETP01" and "SETP1" would otherwise be two
     * spellings of the same ordinal and only one of them could be accounted.
     */
    public function ordinalOf(string $value): ?int
    {
        if ($this->prefix !== '' && ! str_starts_with($value, $this->prefix)) {
            return null;
        }

        $digits = substr($value, strlen($this->prefix));

        if (preg_match('/\A[1-9][0-9]*\z/D', $digits) !== 1) {
            return null;
        }

        return (int) $digits;
    }

    /** @throws NumberOutsideAuthorizedRange|AuthorizationNotActive */
    public function assertCanIssue(int $number, DateTimeImmutable $issuerLocalMoment): void
    {
        if (! $this->covers($number)) {
            throw NumberOutsideAuthorizedRange::forNumber($this, $number);
        }

        if (! $this->isActiveOn($issuerLocalMoment)) {
            throw AuthorizationNotActive::at($this, $issuerLocalMoment);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Cufe;

use DateTimeImmutable;
use InvalidArgumentException;
use Tribux\Dian\DianEnvironment;

/**
 * Canonical inputs for the FEV 1.9 CUFE-SHA384 calculation.
 *
 * Monetary values must already be truncated and formatted with exactly two
 * decimals as required by the DIAN annex. This boundary intentionally avoids
 * binary floating-point normalization.
 */
final readonly class CufeInput
{
    public function __construct(
        public string $invoiceNumber,
        public string $issueDate,
        public string $issueTime,
        public string $lineExtensionAmount,
        public string $vatAmount,
        public string $incAmount,
        public string $icaAmount,
        public string $payableAmount,
        public string $issuerTaxId,
        public string $buyerIdentification,
        public string $technicalKey,
        public DianEnvironment $environment,
    ) {
        self::requireNotEmpty($invoiceNumber, 'invoiceNumber');
        self::requireDate($issueDate);
        self::requireTime($issueTime);
        self::requireAmount($lineExtensionAmount, 'lineExtensionAmount');
        self::requireAmount($vatAmount, 'vatAmount');
        self::requireAmount($incAmount, 'incAmount');
        self::requireAmount($icaAmount, 'icaAmount');
        self::requireAmount($payableAmount, 'payableAmount');
        self::requireIdentifier($issuerTaxId, 'issuerTaxId');
        self::requireIdentifier($buyerIdentification, 'buyerIdentification');
        self::requireNotEmpty($technicalKey, 'technicalKey');
    }

    private static function requireNotEmpty(string $value, string $field): void
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field));
        }
    }

    private static function requireDate(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('issueDate must use a valid YYYY-MM-DD value.');
        }
    }

    private static function requireTime(string $value): void
    {
        if (preg_match('/\A\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException('issueTime must use HH:MM:SS+/-HH:MM.');
        }

        $time = DateTimeImmutable::createFromFormat('!H:i:sP', $value);

        if ($time === false || $time->format('H:i:sP') !== $value) {
            throw new InvalidArgumentException('issueTime must be a valid local time with UTC offset.');
        }
    }

    private static function requireAmount(string $value, string $field): void
    {
        if (preg_match('/\A-?\d+\.\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s must be a decimal string with exactly two decimal places.',
                $field,
            ));
        }
    }

    private static function requireIdentifier(string $value, string $field): void
    {
        if (preg_match('/\A[A-Za-z0-9]+\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s must contain only ASCII letters and digits, without punctuation or separators.',
                $field,
            ));
        }
    }
}

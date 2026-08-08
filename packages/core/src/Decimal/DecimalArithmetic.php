<?php

declare(strict_types=1);

namespace Tribux\Core\Decimal;

use InvalidArgumentException;

/**
 * Arbitrary-length decimal string arithmetic without binary floating point.
 */
final class DecimalArithmetic
{
    public static function add(string $left, string $right): string
    {
        $a = self::parse($left);
        $b = self::parse($right);
        $scale = max($a['scale'], $b['scale']);
        $aDigits = $a['digits'].str_repeat('0', $scale - $a['scale']);
        $bDigits = $b['digits'].str_repeat('0', $scale - $b['scale']);

        if ($a['negative'] === $b['negative']) {
            return self::format(self::addDigits($aDigits, $bDigits), $scale, $a['negative']);
        }

        $comparison = self::compareDigits($aDigits, $bDigits);

        if ($comparison === 0) {
            return self::format('0', $scale, false);
        }

        if ($comparison > 0) {
            return self::format(self::subtractDigits($aDigits, $bDigits), $scale, $a['negative']);
        }

        return self::format(self::subtractDigits($bDigits, $aDigits), $scale, $b['negative']);
    }

    public static function multiply(
        string $left,
        string $right,
        int $scale,
        DecimalRoundingMode $roundingMode,
    ): string {
        self::requireScale($scale);
        $a = self::parse($left);
        $b = self::parse($right);
        $product = self::format(
            self::multiplyDigits($a['digits'], $b['digits']),
            $a['scale'] + $b['scale'],
            $a['negative'] !== $b['negative'],
        );

        return self::quantize($product, $scale, $roundingMode);
    }

    public static function percentage(
        string $base,
        string $percent,
        int $scale,
        DecimalRoundingMode $roundingMode,
    ): string {
        self::requireScale($scale);
        $a = self::parse($base);
        $b = self::parse($percent);
        $product = self::format(
            self::multiplyDigits($a['digits'], $b['digits']),
            $a['scale'] + $b['scale'] + 2,
            $a['negative'] !== $b['negative'],
        );

        return self::quantize($product, $scale, $roundingMode);
    }

    public static function quantize(
        string $value,
        int $scale,
        DecimalRoundingMode $roundingMode,
    ): string {
        self::requireScale($scale);
        $decimal = self::parse($value);

        if ($decimal['scale'] <= $scale) {
            return self::format(
                $decimal['digits'].str_repeat('0', $scale - $decimal['scale']),
                $scale,
                $decimal['negative'],
            );
        }

        $padded = str_pad($decimal['digits'], $decimal['scale'] + 1, '0', STR_PAD_LEFT);
        $discardCount = $decimal['scale'] - $scale;
        $kept = substr($padded, 0, strlen($padded) - $discardCount);
        $discarded = substr($padded, -$discardCount);

        if ($roundingMode === DecimalRoundingMode::HalfUp && $discarded[0] >= '5') {
            $kept = self::addDigits($kept, '1');
        }

        return self::format($kept, $scale, $decimal['negative']);
    }

    /** @return array{digits: non-empty-string, scale: int<0, max>, negative: bool} */
    private static function parse(string $value): array
    {
        if (preg_match('/\A-?\d+(?:\.\d+)?\z/', $value) !== 1) {
            throw new InvalidArgumentException('Value must be a plain decimal string.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $digits = ltrim($integer.$fraction, '0');

        return [
            'digits' => $digits === '' ? '0' : $digits,
            'scale' => strlen($fraction),
            'negative' => $negative && $digits !== '',
        ];
    }

    private static function requireScale(int $scale): void
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Scale must be zero or greater.');
        }
    }

    private static function format(string $digits, int $scale, bool $negative): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $padded = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);

        if ($scale === 0) {
            $value = $padded;
        } else {
            $value = substr($padded, 0, -$scale).'.'.substr($padded, -$scale);
        }

        return $negative && $digits !== '0' ? '-'.$value : $value;
    }

    private static function compareDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    private static function addDigits(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;
            $sum += $leftIndex >= 0 ? (int) $left[$leftIndex--] : 0;
            $sum += $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    /** Subtracts right from left; left must be greater than or equal to right. */
    private static function subtractDigits(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (int) $left[$leftIndex--] - $borrow;
            $digit -= $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) $digit.$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function multiplyDigits(string $left, string $right): string
    {
        if ($left === '0' || $right === '0') {
            return '0';
        }

        $result = '0';
        $rightLength = strlen($right);

        for ($rightIndex = $rightLength - 1; $rightIndex >= 0; --$rightIndex) {
            $multiplier = (int) $right[$rightIndex];
            $carry = 0;
            $partial = '';

            for ($leftIndex = strlen($left) - 1; $leftIndex >= 0; --$leftIndex) {
                $product = ((int) $left[$leftIndex] * $multiplier) + $carry;
                $partial = (string) ($product % 10).$partial;
                $carry = intdiv($product, 10);
            }

            if ($carry > 0) {
                $partial = (string) $carry.$partial;
            }

            $partial .= str_repeat('0', $rightLength - 1 - $rightIndex);
            $result = self::addDigits($result, $partial);
        }

        return ltrim($result, '0') ?: '0';
    }
}

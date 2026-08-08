<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class Fev19Value
{
    public static function text(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field));
        }
    }

    public static function date(string $value, string $field): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf('%s must use a valid YYYY-MM-DD value.', $field));
        }
    }

    public static function time(string $value, string $field): void
    {
        if (preg_match('/\A\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must use HH:MM:SS+/-HH:MM.', $field));
        }

        $time = DateTimeImmutable::createFromFormat('!H:i:sP', $value);

        if ($time === false || $time->format('H:i:sP') !== $value) {
            throw new InvalidArgumentException(sprintf('%s must be a valid local time with UTC offset.', $field));
        }
    }

    public static function amount(string $value, string $field): void
    {
        if (preg_match('/\A\d+\.\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '%s must be a non-negative decimal string with exactly two decimal places.',
                $field,
            ));
        }
    }

    public static function decimal(string $value, string $field): void
    {
        if (preg_match('/\A\d+(?:\.\d{1,6})?\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must be a non-negative decimal string.', $field));
        }
    }

    public static function digits(string $value, string $field): void
    {
        if (preg_match('/\A\d+\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must contain digits only.', $field));
        }
    }

    public static function code(string $value, string $field): void
    {
        if (preg_match('/\A[A-Za-z0-9._-]+\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s contains unsupported characters.', $field));
        }
    }

    public static function currency(string $value): void
    {
        if (preg_match('/\A[A-Z]{3}\z/', $value) !== 1) {
            throw new InvalidArgumentException('currency must be a three-letter uppercase code.');
        }
    }

    public static function sha384(string $value, string $field): void
    {
        if (preg_match('/\A[a-f0-9]{96}\z/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must be a lowercase SHA-384 hexadecimal digest.', $field));
        }
    }
}

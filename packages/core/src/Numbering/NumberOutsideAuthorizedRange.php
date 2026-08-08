<?php

declare(strict_types=1);

namespace Tribux\Core\Numbering;

use DomainException;

final class NumberOutsideAuthorizedRange extends DomainException
{
    public static function forNumber(NumberingAuthorization $authorization, int $number): self
    {
        return new self(sprintf(
            'Number %d is outside the range %d-%d authorized by "%s".',
            $number,
            $authorization->from,
            $authorization->to,
            $authorization->reference,
        ));
    }

    public static function alreadyTaken(NumberingAuthorization $authorization, int $number): self
    {
        return new self(sprintf(
            'Number %d of authorization "%s" already belongs to another document.',
            $number,
            $authorization->reference,
        ));
    }

    public static function exhausted(NumberingAuthorization $authorization): self
    {
        return new self(sprintf(
            'Authorization "%s" has no numbers left in the range %d-%d.',
            $authorization->reference,
            $authorization->from,
            $authorization->to,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Core\Numbering;

use DateTimeImmutable;
use DomainException;

final class AuthorizationNotActive extends DomainException
{
    public static function at(NumberingAuthorization $authorization, DateTimeImmutable $moment): self
    {
        return new self(sprintf(
            'Authorization "%s" is only valid from %s to %s, and %s is outside it.',
            $authorization->reference,
            $authorization->validFrom->format('Y-m-d'),
            $authorization->validTo->format('Y-m-d'),
            $moment->format('Y-m-d'),
        ));
    }
}

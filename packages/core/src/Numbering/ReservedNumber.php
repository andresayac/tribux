<?php

declare(strict_types=1);

namespace Tribux\Core\Numbering;

/**
 * A number that has been taken from an authorization and belongs to one
 * document from that moment on.
 *
 * It keeps both the ordinal and the formatted value: the ordinal is what the
 * range is accounted in, and the formatted value is what the document carries.
 */
final readonly class ReservedNumber
{
    public function __construct(
        public string $authorizationReference,
        public string $prefix,
        public int $ordinal,
        public string $value,
    ) {}

    public static function from(NumberingAuthorization $authorization, int $ordinal): self
    {
        return new self(
            $authorization->reference,
            $authorization->prefix,
            $ordinal,
            $authorization->format($ordinal),
        );
    }
}

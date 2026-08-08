<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

use DomainException;

final class IllegalStatusTransition extends DomainException
{
    private function __construct(
        public readonly InvoiceStatus $from,
        public readonly InvoiceStatus $to,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function between(InvoiceStatus $from, InvoiceStatus $to): self
    {
        return new self($from, $to, sprintf(
            'Invoice status cannot move from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}

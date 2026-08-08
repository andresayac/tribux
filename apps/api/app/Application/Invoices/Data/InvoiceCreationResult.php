<?php

declare(strict_types=1);

namespace App\Application\Invoices\Data;

final readonly class InvoiceCreationResult
{
    public function __construct(
        public InvoiceView $invoice,
        public bool $replayed,
    ) {}
}

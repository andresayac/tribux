<?php

declare(strict_types=1);

namespace App\Application\Invoices\Contracts;

use App\Application\Invoices\Data\InvoiceCreationResult;
use App\Application\Invoices\Data\InvoiceView;
use Tribux\Core\Invoice\Invoice;

interface InvoiceRepository
{
    /** @param array<string, mixed> $payload */
    public function createIdempotently(
        Invoice $invoice,
        array $payload,
        string $idempotencyKey,
        string $requestHash,
    ): InvoiceCreationResult;

    public function find(string $invoiceId): ?InvoiceView;
}

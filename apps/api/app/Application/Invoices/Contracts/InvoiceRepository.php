<?php

declare(strict_types=1);

namespace App\Application\Invoices\Contracts;

use App\Application\Invoices\Data\InvoiceCreationResult;
use App\Application\Invoices\Data\InvoiceView;
use App\Application\Invoices\Data\StoredInvoice;
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

    /** Reads the invoice together with the immutable payload it was created from. */
    public function load(string $invoiceId): ?StoredInvoice;

    /**
     * Persist the identity the generated document carries.
     *
     * Idempotent: writing the same number and CUFE again is a no-op, and
     * writing different ones over an existing pair is refused, because a
     * document that already has a CUFE has already been described to DIAN.
     */
    public function recordDocumentIdentity(string $invoiceId, string $number, string $cufe): void;
}

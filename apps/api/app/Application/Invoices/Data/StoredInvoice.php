<?php

declare(strict_types=1);

namespace App\Application\Invoices\Data;

use DateTimeImmutable;
use Tribux\Core\Invoice\InvoiceStatus;

/**
 * An invoice as it was accepted, including the immutable request payload.
 *
 * A worker rebuilding a document months later reads this rather than an HTTP
 * request, so it reproduces exactly what the issuer sent.
 */
final readonly class StoredInvoice
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $issuerId,
        public InvoiceStatus $status,
        public ?string $number,
        public ?string $cufe,
        public array $payload,
        public DateTimeImmutable $createdAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing\Data;

use App\Application\Invoices\Processing\StatusChangeSource;
use DateTimeImmutable;
use Tribux\Core\Invoice\InvoiceStatus;

final readonly class StatusChange
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public ?InvoiceStatus $from,
        public InvoiceStatus $to,
        public StatusChangeSource $source,
        public DateTimeImmutable $occurredAt,
        public ?string $attemptId = null,
    ) {}
}

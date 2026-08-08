<?php

declare(strict_types=1);

namespace App\Application\Invoices;

use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Data\InvoiceView;
use App\Application\Invoices\Exceptions\InvoiceNotFound;

final readonly class GetInvoice
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function execute(string $invoiceId): InvoiceView
    {
        return $this->invoices->find($invoiceId) ?? throw new InvoiceNotFound($invoiceId);
    }
}

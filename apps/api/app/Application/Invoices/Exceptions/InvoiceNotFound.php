<?php

declare(strict_types=1);

namespace App\Application\Invoices\Exceptions;

use RuntimeException;

final class InvoiceNotFound extends RuntimeException
{
    public function __construct(string $invoiceId)
    {
        parent::__construct(sprintf('Invoice "%s" was not found.', $invoiceId));
    }
}

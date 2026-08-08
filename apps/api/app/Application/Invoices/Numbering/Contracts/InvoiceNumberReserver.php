<?php

declare(strict_types=1);

namespace App\Application\Invoices\Numbering\Contracts;

use DateTimeImmutable;
use Tribux\Core\Numbering\AuthorizationNotActive;
use Tribux\Core\Numbering\NumberingAuthorization;
use Tribux\Core\Numbering\NumberOutsideAuthorizedRange;
use Tribux\Core\Numbering\ReservedNumber;

interface InvoiceNumberReserver
{
    /**
     * Take the next number of an authorization for one invoice.
     *
     * Idempotent per invoice: an invoice that already holds a number gets the
     * same one back. A number is never returned to the pool, so a failed or
     * ambiguous submission keeps it consumed rather than risking a duplicate
     * document number at DIAN.
     *
     * @param  DateTimeImmutable  $issuerLocalMoment  the issue moment expressed
     *                                                in the issuer time zone
     *
     * @throws AuthorizationNotActive
     * @throws NumberOutsideAuthorizedRange
     */
    public function reserve(
        string $issuerId,
        string $invoiceId,
        NumberingAuthorization $authorization,
        DateTimeImmutable $issuerLocalMoment,
    ): ReservedNumber;

    public function find(string $invoiceId): ?ReservedNumber;
}

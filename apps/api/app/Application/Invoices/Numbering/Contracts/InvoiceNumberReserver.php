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

    /**
     * Take one specific number, as when the client supplied it.
     *
     * It goes through the same ledger as a reservation, so a supplied number
     * cannot collide with one Tribux hands out later.
     *
     * @throws AuthorizationNotActive
     * @throws NumberOutsideAuthorizedRange when the number is outside the range
     *                                      or already taken by another invoice
     */
    public function claim(
        string $issuerId,
        string $invoiceId,
        NumberingAuthorization $authorization,
        int $ordinal,
        DateTimeImmutable $issuerLocalMoment,
    ): ReservedNumber;

    public function find(string $invoiceId): ?ReservedNumber;
}

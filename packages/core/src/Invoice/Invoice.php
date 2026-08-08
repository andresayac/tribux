<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice;

use DateTimeImmutable;
use InvalidArgumentException;
use Tribux\Core\Party\Party;

final readonly class Invoice
{
    /** @param non-empty-list<InvoiceLine> $lines */
    public function __construct(
        public InvoiceId $id,
        public string $issuerId,
        public Party $customer,
        public array $lines,
        public DateTimeImmutable $createdAt,
        public ?string $number = null,
        public InvoiceStatus $status = InvoiceStatus::Queued,
    ) {
        if (trim($issuerId) === '' || strlen($issuerId) > 100) {
            throw new InvalidArgumentException('Issuer ID must be non-empty and at most 100 characters.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Invoice must contain at least one line.');
        }

        $currency = null;
        foreach ($lines as $line) {
            if (!$line instanceof InvoiceLine) {
                throw new InvalidArgumentException('Invoice lines must contain only InvoiceLine values.');
            }

            $currency ??= $line->unitPrice->currency;
            if ($line->unitPrice->currency !== $currency) {
                throw new InvalidArgumentException('All invoice lines must use the same currency.');
            }
        }

        if ($number !== null && (trim($number) === '' || strlen($number) > 100)) {
            throw new InvalidArgumentException('Invoice number must be null or a non-empty value of at most 100 characters.');
        }
    }
}

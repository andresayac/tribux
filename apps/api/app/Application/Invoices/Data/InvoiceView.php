<?php

declare(strict_types=1);

namespace App\Application\Invoices\Data;

use DateTimeImmutable;
use Tribux\Core\Invoice\InvoiceStatus;

final readonly class InvoiceView
{
    public function __construct(
        public string $id,
        public string $issuerId,
        public InvoiceStatus $status,
        public ?string $number,
        public ?string $cufe,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @return array{id:string,issuer_id:string,status:string,number:?string,cufe:?string,created_at:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issuer_id' => $this->issuerId,
            'status' => $this->status->value,
            'number' => $this->number,
            'cufe' => $this->cufe,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}

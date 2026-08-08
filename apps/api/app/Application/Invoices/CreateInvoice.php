<?php

declare(strict_types=1);

namespace App\Application\Invoices;

use App\Application\Contracts\Clock;
use App\Application\Contracts\IdGenerator;
use App\Application\Invoices\Contracts\InvoiceRepository;
use App\Application\Invoices\Data\InvoiceCreationResult;
use JsonException;

final readonly class CreateInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceMapper $mapper,
        private Clock $clock,
        private IdGenerator $ids,
    ) {}

    /** @param array<string, mixed> $payload
     * @throws JsonException
     */
    public function execute(array $payload, string $idempotencyKey): InvoiceCreationResult
    {
        $normalized = self::normalize($payload);
        $requestHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $createdAt = $this->clock->now();
        $invoice = $this->mapper->fromArray($this->ids->generate(), $createdAt, $payload);

        return $this->invoices->createIdempotently($invoice, $payload, $idempotencyKey, $requestHash);
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::normalize(...), $value);
    }
}

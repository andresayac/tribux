<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The published example request, loaded from disk.
 *
 * Tests build on the same file operators copy, so an example that stops
 * satisfying the contract fails the suite instead of rotting in the repository.
 */
final readonly class InvoicePayload
{
    /** @return array<string, mixed> */
    public static function minimal(): array
    {
        $path = __DIR__.'/../../../../examples/invoice.minimal.json';

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);

        return $payload;
    }
}

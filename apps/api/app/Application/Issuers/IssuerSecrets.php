<?php

declare(strict_types=1);

namespace App\Application\Issuers;

use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * The software PIN and the resolution technical key, held in memory only.
 *
 * Both feed hashes DIAN verifies, and neither may ever reach a log line, an
 * evidence row, an exception message or a serialized job payload. Serialization
 * is refused outright rather than redacted, so a queued job that tries to carry
 * this object fails loudly at development time instead of leaking in
 * production.
 */
final class IssuerSecrets
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $softwarePin,
        #[SensitiveParameter]
        private readonly string $technicalKey,
    ) {
        if (trim($softwarePin) === '') {
            throw new InvalidArgumentException('The software PIN cannot be empty.');
        }

        if (trim($technicalKey) === '') {
            throw new InvalidArgumentException('The technical key cannot be empty.');
        }
    }

    public function softwarePin(): string
    {
        return $this->softwarePin;
    }

    public function technicalKey(): string
    {
        return $this->technicalKey;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['softwarePin' => '[redacted]', 'technicalKey' => '[redacted]'];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new LogicException('Issuer secrets must not be serialized. Resolve them inside the stage that needs them.');
    }

    /** @return list<string> */
    public function __sleep(): array
    {
        throw new LogicException('Issuer secrets must not be serialized. Resolve them inside the stage that needs them.');
    }
}

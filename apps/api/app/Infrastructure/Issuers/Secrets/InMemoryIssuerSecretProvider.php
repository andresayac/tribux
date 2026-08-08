<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers\Secrets;

use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\Exceptions\SecretNotAvailable;
use App\Application\Issuers\IssuerSecrets;

/** Test double. Never bind this outside tests or a local dry run. */
final readonly class InMemoryIssuerSecretProvider implements IssuerSecretProvider
{
    /** @param array<string, IssuerSecrets> $secrets keyed by credential reference */
    public function __construct(private array $secrets = []) {}

    public function forReference(string $credentialReference): IssuerSecrets
    {
        return $this->secrets[$credentialReference]
            ?? throw SecretNotAvailable::missingFile($credentialReference, 'in-memory secrets');
    }
}

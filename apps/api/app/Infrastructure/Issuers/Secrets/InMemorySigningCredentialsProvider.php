<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers\Secrets;

use App\Application\Issuers\Contracts\SigningCredentialsProvider;
use App\Application\Issuers\Exceptions\SecretNotAvailable;
use Tribux\Dian\Signing\SigningCredentials;

/** Test double. Never bind this outside tests or a local dry run. */
final readonly class InMemorySigningCredentialsProvider implements SigningCredentialsProvider
{
    /** @param array<string, SigningCredentials> $credentials keyed by credential reference */
    public function __construct(private array $credentials = []) {}

    public function forReference(string $credentialReference): SigningCredentials
    {
        return $this->credentials[$credentialReference]
            ?? throw SecretNotAvailable::missingFile($credentialReference, 'in-memory signing credentials');
    }
}

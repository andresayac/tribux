<?php

declare(strict_types=1);

namespace App\Application\Issuers\Contracts;

use App\Application\Issuers\Exceptions\SecretNotAvailable;
use App\Application\Issuers\IssuerSecrets;

/**
 * Resolves the software PIN and technical key for a credential reference.
 *
 * Kept separate from signing credentials so the build stage never loads a
 * private key it does not need.
 */
interface IssuerSecretProvider
{
    /** @throws SecretNotAvailable */
    public function forReference(string $credentialReference): IssuerSecrets;
}

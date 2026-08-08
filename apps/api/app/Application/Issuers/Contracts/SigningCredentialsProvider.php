<?php

declare(strict_types=1);

namespace App\Application\Issuers\Contracts;

use App\Application\Issuers\Exceptions\SecretNotAvailable;
use Tribux\Dian\Signing\SigningCredentials;

/**
 * Resolves signing material for a credential reference.
 *
 * The returned SigningCredentials keeps the private key encapsulated: it can
 * sign and expose the certificate chain, but never hands the key back. Only the
 * signing stage may call this.
 */
interface SigningCredentialsProvider
{
    /** @throws SecretNotAvailable */
    public function forReference(string $credentialReference): SigningCredentials;
}

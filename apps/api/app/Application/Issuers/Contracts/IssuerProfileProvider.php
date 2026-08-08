<?php

declare(strict_types=1);

namespace App\Application\Issuers\Contracts;

use App\Application\Issuers\Exceptions\IssuerNotConfigured;
use App\Application\Issuers\IssuerProfile;

/**
 * Resolves the non-secret DIAN configuration of an issuer.
 *
 * Implementations must never return secrets: the software PIN, the technical
 * key and the signing material are resolved separately and only inside the
 * stage that needs them.
 */
interface IssuerProfileProvider
{
    /** @throws IssuerNotConfigured */
    public function get(string $issuerId): IssuerProfile;
}

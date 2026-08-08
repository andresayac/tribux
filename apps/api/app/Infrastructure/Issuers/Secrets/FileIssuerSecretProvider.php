<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers\Secrets;

use App\Application\Issuers\Contracts\IssuerSecretProvider;
use App\Application\Issuers\IssuerSecrets;

/**
 * Reads the software PIN and technical key from a secret mount:
 *
 *   {TRIBUX_SECRETS_PATH}/{credential_reference}/software_pin
 *   {TRIBUX_SECRETS_PATH}/{credential_reference}/technical_key
 *
 * Values are read on demand and never cached, so rotating a mounted secret does
 * not require restarting a worker mid-shift.
 */
final readonly class FileIssuerSecretProvider implements IssuerSecretProvider
{
    public const string SOFTWARE_PIN = 'software_pin';

    public const string TECHNICAL_KEY = 'technical_key';

    public function __construct(private MountedSecretFiles $files) {}

    public function forReference(string $credentialReference): IssuerSecrets
    {
        return new IssuerSecrets(
            softwarePin: $this->files->line($credentialReference, self::SOFTWARE_PIN),
            technicalKey: $this->files->line($credentialReference, self::TECHNICAL_KEY),
        );
    }
}

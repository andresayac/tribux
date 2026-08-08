<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers;

use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Exceptions\IssuerNotConfigured;
use App\Application\Issuers\IssuerProfile;

/**
 * In-memory profiles. Used as the test double and as the base other providers
 * hydrate into.
 */
final readonly class ArrayIssuerProfileProvider implements IssuerProfileProvider
{
    /** @var array<string, IssuerProfile> */
    private array $profiles;

    /** @param list<IssuerProfile> $profiles */
    public function __construct(array $profiles = [], private string $source = 'the in-memory issuer registry')
    {
        $indexed = [];

        foreach ($profiles as $profile) {
            $indexed[$profile->reference] = $profile;
        }

        $this->profiles = $indexed;
    }

    public function get(string $issuerId): IssuerProfile
    {
        return $this->profiles[$issuerId] ?? throw IssuerNotConfigured::withReference($issuerId, $this->source);
    }
}

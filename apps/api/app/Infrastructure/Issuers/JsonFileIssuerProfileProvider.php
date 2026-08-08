<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers;

use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Application\Issuers\Exceptions\IssuerConfigurationInvalid;
use App\Application\Issuers\Exceptions\IssuerNotConfigured;
use App\Application\Issuers\IssuerProfile;
use App\Application\Issuers\IssuerProfileFactory;
use InvalidArgumentException;
use JsonException;

/**
 * Reads issuer profiles from a mounted JSON file.
 *
 * A file keeps the nested DIAN configuration expressible without inventing a
 * flat environment-variable dialect, and keeps it out of Git. The file holds no
 * secrets: it only names the credential reference a secret provider resolves.
 *
 * Every issuer in the file is validated on load, so a typo in one issuer is
 * reported before it silently breaks a submission months later.
 */
final class JsonFileIssuerProfileProvider implements IssuerProfileProvider
{
    /** @var array<string, IssuerProfile>|null */
    private ?array $profiles = null;

    public function __construct(
        private readonly ?string $path,
        private readonly IssuerProfileFactory $factory = new IssuerProfileFactory,
    ) {}

    public function get(string $issuerId): IssuerProfile
    {
        $profiles = $this->profiles ??= $this->load();

        return $profiles[$issuerId] ?? throw IssuerNotConfigured::withReference($issuerId, $this->source());
    }

    /** @return array<string, IssuerProfile> */
    private function load(): array
    {
        if ($this->path === null || trim($this->path) === '') {
            return [];
        }

        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw IssuerConfigurationInvalid::unreadable($this->path);
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw IssuerConfigurationInvalid::unreadable($this->path);
        }

        try {
            $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw IssuerConfigurationInvalid::notJson($this->path);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw IssuerConfigurationInvalid::notJson($this->path);
        }

        $profiles = [];

        foreach ($decoded as $issuerId => $data) {
            $issuerId = (string) $issuerId;

            if (! is_array($data) || array_is_list($data)) {
                throw IssuerConfigurationInvalid::forIssuer(
                    $this->path,
                    $issuerId,
                    new InvalidArgumentException('the entry must be an object'),
                );
            }

            /** @var array<string, mixed> $data */
            try {
                $profiles[$issuerId] = $this->factory->fromArray($issuerId, $data);
            } catch (InvalidArgumentException $exception) {
                throw IssuerConfigurationInvalid::forIssuer($this->path, $issuerId, $exception);
            }
        }

        return $profiles;
    }

    private function source(): string
    {
        return $this->path === null || trim($this->path) === ''
            ? 'no issuer configuration file (set TRIBUX_ISSUERS_FILE)'
            : sprintf('the issuer configuration file "%s"', $this->path);
    }
}

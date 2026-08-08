<?php

declare(strict_types=1);

namespace App\Infrastructure\Issuers\Secrets;

use App\Application\Issuers\Exceptions\SecretNotAvailable;

/**
 * Reads one-secret-per-file mounts, the shape Docker and Kubernetes already
 * produce.
 *
 * A credential reference becomes a directory name, so it is validated as a
 * single safe path segment before touching the filesystem: an issuer file is
 * operator input, and "../../etc" must not resolve to anything.
 */
final readonly class MountedSecretFiles
{
    public function __construct(private ?string $basePath) {}

    public function has(string $reference, string $name): bool
    {
        if ($this->basePath === null || trim($this->basePath) === '') {
            return false;
        }

        $path = $this->path($reference, $name);

        return is_file($path) && is_readable($path);
    }

    /** Reads a single-line secret, dropping the trailing newline editors add. */
    public function line(string $reference, string $name): string
    {
        return trim($this->contents($reference, $name), "\r\n");
    }

    public function contents(string $reference, string $name): string
    {
        if ($this->basePath === null || trim($this->basePath) === '') {
            throw SecretNotAvailable::unconfigured($reference);
        }

        $path = $this->path($reference, $name);

        if (! is_file($path) || ! is_readable($path)) {
            throw SecretNotAvailable::missingFile($reference, $name);
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw SecretNotAvailable::unreadable($reference, $name);
        }

        return $contents;
    }

    private function path(string $reference, string $name): string
    {
        if (preg_match('/\A[A-Za-z0-9._-]+\z/D', $reference) !== 1 || str_contains($reference, '..')) {
            throw SecretNotAvailable::unusableReference($reference);
        }

        return rtrim((string) $this->basePath, '/\\').DIRECTORY_SEPARATOR.$reference.DIRECTORY_SEPARATOR.$name;
    }
}

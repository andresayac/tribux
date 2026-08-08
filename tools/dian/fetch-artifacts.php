<?php

declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 2);
$manifestPath = $argv[1] ?? $repositoryRoot.'/resources/dian/fev/1.9/manifest.json';

try {
    $manifestJson = file_get_contents($manifestPath);

    if ($manifestJson === false) {
        throw new \RuntimeException(sprintf('Cannot read manifest: %s', $manifestPath));
    }

    /** @var array{default_target:string,artifacts:list<array{id:string,file:string,url:string,bytes:int,sha256:string,sha384_base64?:string}>} $manifest */
    $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
    $targetDirectory = $argv[2] ?? $repositoryRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $manifest['default_target']);

    if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
        throw new \RuntimeException(sprintf('Cannot create target directory: %s', $targetDirectory));
    }

    foreach ($manifest['artifacts'] as $artifact) {
        if (basename($artifact['file']) !== $artifact['file']) {
            throw new \RuntimeException(sprintf('Unsafe artifact filename: %s', $artifact['file']));
        }

        if (filter_var($artifact['url'], FILTER_VALIDATE_URL) === false || ! str_starts_with($artifact['url'], 'https://')) {
            throw new \RuntimeException(sprintf('Artifact URL must use HTTPS: %s', $artifact['id']));
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $artifact['sha256']) !== 1) {
            throw new \RuntimeException(sprintf('Invalid SHA-256 in manifest: %s', $artifact['id']));
        }

        $destination = $targetDirectory.DIRECTORY_SEPARATOR.$artifact['file'];

        if (is_file($destination) && hash_file('sha256', $destination) === $artifact['sha256']) {
            if (isset($artifact['sha384_base64'])) {
                $actualSha384 = base64_encode(hash_file('sha384', $destination, true));

                if (! hash_equals($artifact['sha384_base64'], $actualSha384)) {
                    throw new \RuntimeException(sprintf('SHA-384 check failed for %s.', $artifact['id']));
                }
            }

            fwrite(STDOUT, sprintf("verified  %s\n", $artifact['id']));
            continue;
        }

        if (is_file($destination)) {
            throw new \RuntimeException(sprintf(
                'Existing artifact has an unexpected hash; remove it after inspection: %s',
                $destination,
            ));
        }

        $temporary = tempnam($targetDirectory, '.tribux-download-');

        if ($temporary === false) {
            throw new \RuntimeException('Cannot allocate a temporary download file.');
        }

        try {
            $source = false;
            $target = false;

            try {
                $source = fopen($artifact['url'], 'rb');
                $target = fopen($temporary, 'wb');

                if ($source === false || $target === false) {
                    throw new \RuntimeException(sprintf('Cannot download artifact: %s', $artifact['id']));
                }

                if (stream_copy_to_stream($source, $target) === false) {
                    throw new \RuntimeException(sprintf('Download stream failed: %s', $artifact['id']));
                }
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }

                if (is_resource($target)) {
                    fclose($target);
                }
            }

            $actualBytes = filesize($temporary);
            $actualHash = hash_file('sha256', $temporary);

            if ($actualBytes !== $artifact['bytes'] || $actualHash !== $artifact['sha256']) {
                throw new \RuntimeException(sprintf(
                    'Integrity check failed for %s (bytes %s, sha256 %s).',
                    $artifact['id'],
                    $actualBytes === false ? 'unknown' : (string) $actualBytes,
                    $actualHash,
                ));
            }

            if (isset($artifact['sha384_base64'])) {
                $actualSha384 = base64_encode(hash_file('sha384', $temporary, true));

                if (! hash_equals($artifact['sha384_base64'], $actualSha384)) {
                    throw new \RuntimeException(sprintf('SHA-384 check failed for %s.', $artifact['id']));
                }
            }

            if (! rename($temporary, $destination)) {
                throw new \RuntimeException(sprintf('Cannot store verified artifact: %s', $destination));
            }

            fwrite(STDOUT, sprintf("downloaded %s\n", $artifact['id']));
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
} catch (\JsonException|\RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
} catch (\Throwable $exception) {
    fwrite(STDERR, sprintf('Unexpected download failure: %s%s', $exception->getMessage(), PHP_EOL));
    exit(1);
}

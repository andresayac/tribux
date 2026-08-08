<?php

declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 2);
$zipPath = $argv[1] ?? $repositoryRoot.'/var/dian/fev/1.9/Caja-de-herramientas-FE-V19-V2026.zip';
$targetDirectory = $argv[2] ?? $repositoryRoot.'/var/dian/fev/1.9/toolbox';
$expectedSha256 = '2d6002d0a446ed9016ceb584cd18ae9b45ee532eb49d45cd999610d01b7266b3';

try {
    if (! is_file($zipPath) || hash_file('sha256', $zipPath) !== $expectedSha256) {
        throw new \RuntimeException('The FEV 1.9 toolbox ZIP is missing or has an unexpected hash.');
    }

    if (file_exists($targetDirectory)) {
        throw new \RuntimeException(sprintf('Extraction target already exists: %s', $targetDirectory));
    }

    $archive = new ZipArchive();

    if ($archive->open($zipPath) !== true) {
        throw new \RuntimeException(sprintf('Cannot open toolbox ZIP: %s', $zipPath));
    }

    try {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);

            if ($entry === false) {
                throw new \RuntimeException(sprintf('Cannot inspect ZIP entry %d.', $index));
            }

            $normalized = str_replace('\\', '/', $entry);
            $segments = explode('/', $normalized);

            if (
                $normalized === ''
                || str_contains($normalized, "\0")
                || str_starts_with($normalized, '/')
                || preg_match('/\A[A-Za-z]:/', $normalized) === 1
                || in_array('..', $segments, true)
            ) {
                throw new \RuntimeException(sprintf('Unsafe ZIP entry: %s', $entry));
            }
        }

        $parentDirectory = dirname($targetDirectory);

        if (! is_dir($parentDirectory) && ! mkdir($parentDirectory, 0775, true) && ! is_dir($parentDirectory)) {
            throw new \RuntimeException(sprintf('Cannot create extraction parent: %s', $parentDirectory));
        }

        $temporaryDirectory = $parentDirectory.DIRECTORY_SEPARATOR.'.tribux-toolbox-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0775)) {
            throw new \RuntimeException(sprintf('Cannot create extraction directory: %s', $temporaryDirectory));
        }

        if (! $archive->extractTo($temporaryDirectory)) {
            throw new \RuntimeException('Toolbox extraction failed.');
        }

        if (! rename($temporaryDirectory, $targetDirectory)) {
            throw new \RuntimeException(sprintf('Cannot publish extracted toolbox: %s', $targetDirectory));
        }

        fwrite(STDOUT, sprintf("extracted %d files to %s%s", $archive->numFiles, $targetDirectory, PHP_EOL));
    } finally {
        $archive->close();
    }
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

$repositoryRoot = dirname(__DIR__, 2);
$zipPath = $argv[1] ?? $repositoryRoot.'/var/tools/saxon/12.10/SaxonHE12-10J.zip';
$targetDirectory = $argv[2] ?? $repositoryRoot.'/var/tools/saxon/12.10/dist';
$expectedSha256 = '1c7db9f726df835349c64edd631de0310eca31291100230064eba153f607b0be';
$expectedFiles = [
    'saxon-he-12.10.jar' => '89bcd071666c3268ee8c5e91e9c9812ad52b3e49d38a3a971720fdb428bfeea9',
    'lib/xmlresolver-5.3.3.jar' => '1fe4d5b92f708dcdb82dbce12919e0171e6b5ca62c6dca6220483625098feb5f',
    'lib/xmlresolver-5.3.3-data.jar' => 'b0c487ad2f3e558be8d829c916d2458d10aca6a5bafa7a4d0524b70845e48a5c',
    'notices/LICENSE.txt' => null,
];

try {
    if (! is_file($zipPath) || hash_file('sha256', $zipPath) !== $expectedSha256) {
        throw new RuntimeException('The SaxonJ-HE ZIP is missing or has an unexpected hash.');
    }

    if (file_exists($targetDirectory)) {
        throw new RuntimeException(sprintf('Extraction target already exists: %s', $targetDirectory));
    }

    $archive = new ZipArchive();

    if ($archive->open($zipPath) !== true) {
        throw new RuntimeException(sprintf('Cannot open SaxonJ-HE ZIP: %s', $zipPath));
    }

    try {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);

            if ($entry === false) {
                throw new RuntimeException(sprintf('Cannot inspect ZIP entry %d.', $index));
            }

            $normalized = str_replace('\\', '/', $entry);

            if (
                $normalized === ''
                || str_contains($normalized, "\0")
                || str_starts_with($normalized, '/')
                || preg_match('/\A[A-Za-z]:/', $normalized) === 1
                || in_array('..', explode('/', $normalized), true)
            ) {
                throw new RuntimeException(sprintf('Unsafe ZIP entry: %s', $entry));
            }
        }

        $parentDirectory = dirname($targetDirectory);

        if (! is_dir($parentDirectory) && ! mkdir($parentDirectory, 0775, true) && ! is_dir($parentDirectory)) {
            throw new RuntimeException(sprintf('Cannot create extraction parent: %s', $parentDirectory));
        }

        $temporaryDirectory = $parentDirectory.DIRECTORY_SEPARATOR.'.tribux-saxon-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0775) || ! $archive->extractTo($temporaryDirectory)) {
            throw new RuntimeException('SaxonJ-HE extraction failed.');
        }

        foreach ($expectedFiles as $relativePath => $expectedHash) {
            $path = $temporaryDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (! is_file($path) || ($expectedHash !== null && hash_file('sha256', $path) !== $expectedHash)) {
                throw new RuntimeException(sprintf('Extracted Saxon file failed verification: %s', $relativePath));
            }
        }

        if (! rename($temporaryDirectory, $targetDirectory)) {
            throw new RuntimeException(sprintf('Cannot publish extracted Saxon runtime: %s', $targetDirectory));
        }

        fwrite(STDOUT, sprintf("extracted SaxonJ-HE to %s%s", $targetDirectory, PHP_EOL));
    } finally {
        $archive->close();
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

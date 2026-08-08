<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class Fev19ZipPackageBuilder
{
    private const int ZIP_EPOCH = 315532800;

    public function build(
        string $zipFileName,
        Fev19SubmissionMode $mode,
        Fev19SubmissionDocument ...$documents,
    ): Fev19SubmissionPackage {
        $documents = array_values($documents);

        if (preg_match('/\Az[0-9]{15}[0-9A-F]{8}\.zip\z/D', $zipFileName) !== 1) {
            throw new InvalidArgumentException('ZIP filename does not follow the FEV 1.9 naming structure.');
        }

        $this->assertCardinality($mode, count($documents));
        $this->assertUniqueNames($documents);
        $this->assertCommonNameParts($zipFileName, $documents);

        usort(
            $documents,
            static fn (Fev19SubmissionDocument $left, Fev19SubmissionDocument $right): int => strcmp(
                $left->fileName,
                $right->fileName,
            ),
        );

        $contents = $this->createArchive($documents);

        return new Fev19SubmissionPackage(
            fileName: $zipFileName,
            contents: $contents,
            documentFileNames: array_map(
                static fn (Fev19SubmissionDocument $document): string => $document->fileName,
                $documents,
            ),
            mode: $mode,
        );
    }

    private function assertCardinality(Fev19SubmissionMode $mode, int $count): void
    {
        if ($mode === Fev19SubmissionMode::Synchronous && $count !== 1) {
            throw new InvalidArgumentException('A synchronous FEV 1.9 ZIP must contain exactly one XML document.');
        }

        if ($mode === Fev19SubmissionMode::Asynchronous && ($count < 1 || $count > 50)) {
            throw new InvalidArgumentException('An asynchronous FEV 1.9 ZIP must contain between one and 50 XML documents.');
        }
    }

    /** @param list<Fev19SubmissionDocument> $documents */
    private function assertUniqueNames(array $documents): void
    {
        $names = array_map(
            static fn (Fev19SubmissionDocument $document): string => $document->fileName,
            $documents,
        );

        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('A FEV 1.9 ZIP cannot contain duplicate document filenames.');
        }
    }

    /** @param list<Fev19SubmissionDocument> $documents */
    private function assertCommonNameParts(string $zipFileName, array $documents): void
    {
        $zipCommonPart = substr($zipFileName, 1, 15);

        foreach ($documents as $document) {
            if ($document->commonNamePart() !== $zipCommonPart) {
                throw new InvalidArgumentException(
                    'ZIP and document filenames must use the same issuer, provider code, and calendar year.',
                );
            }
        }
    }

    /** @param list<Fev19SubmissionDocument> $documents */
    private function createArchive(array $documents): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'tribux-fev19-');

        if (! is_string($temporaryPath)) {
            throw new RuntimeException('Could not allocate a temporary file for the FEV 1.9 ZIP.');
        }

        $archive = new ZipArchive();
        $isOpen = false;

        try {
            $openResult = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new RuntimeException('Could not create the FEV 1.9 ZIP archive.');
            }

            $isOpen = true;

            foreach ($documents as $document) {
                if (! $archive->addFromString($document->fileName, $document->xml)
                    || ! $archive->setCompressionName($document->fileName, ZipArchive::CM_STORE)
                    || ! $archive->setMtimeName($document->fileName, self::ZIP_EPOCH)) {
                    throw new RuntimeException('Could not add a document to the FEV 1.9 ZIP archive.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Could not finalize the FEV 1.9 ZIP archive.');
            }

            $isOpen = false;
            $contents = file_get_contents($temporaryPath);

            if (! is_string($contents)) {
                throw new RuntimeException('Could not read the generated FEV 1.9 ZIP archive.');
            }

            return $contents;
        } finally {
            if ($isOpen) {
                $archive->close();
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Artifacts;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tribux\Dian\Documents\DianDocumentType;

final readonly class Fev19ArtifactSet
{
    /** @param array<string, string> $documentSchemas */
    private function __construct(
        private array $documentSchemas,
        public string $compiledSchematron,
    ) {
    }

    public static function discover(string $toolboxRoot): self
    {
        if (! is_dir($toolboxRoot)) {
            throw new InvalidArgumentException(sprintf('FEV 1.9 toolbox directory not found: %s', $toolboxRoot));
        }

        $schemas = [];

        foreach (DianDocumentType::cases() as $type) {
            $schemas[$type->value] = self::findUniqueFile($toolboxRoot, $type->xsdFileName());
        }

        return new self(
            documentSchemas: $schemas,
            compiledSchematron: self::findUniqueFile($toolboxRoot, 'DIAN-UBL21-model-compiled.xsl'),
        );
    }

    public function xsdFor(DianDocumentType $type): string
    {
        return $this->documentSchemas[$type->value];
    }

    private static function findUniqueFile(string $root, string $fileName): string
    {
        $matches = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getFilename() === $fileName) {
                $matches[] = $file->getPathname();
            }
        }

        if (count($matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Expected exactly one %s in the FEV 1.9 toolbox; found %d.',
                $fileName,
                count($matches),
            ));
        }

        return $matches[0];
    }
}

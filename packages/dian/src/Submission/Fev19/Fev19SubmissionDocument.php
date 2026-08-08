<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

use InvalidArgumentException;
use Tribux\Dian\Documents\DianDocumentType;

final readonly class Fev19SubmissionDocument
{
    public function __construct(
        public DianDocumentType $type,
        public string $fileName,
        public string $xml,
    ) {
        if (preg_match('/\A(?:fv|nc|nd|ar|ad)[0-9]{15}[0-9A-F]{8}\.xml\z/D', $fileName) !== 1) {
            throw new InvalidArgumentException('Document filename does not follow the FEV 1.9 naming structure.');
        }

        if (! str_starts_with($fileName, $type->filePrefix())) {
            throw new InvalidArgumentException('Document filename prefix does not match its document type.');
        }

        if (trim($xml) === '') {
            throw new InvalidArgumentException('Submission document XML cannot be empty.');
        }
    }

    public function commonNamePart(): string
    {
        return substr($this->fileName, 2, 15);
    }
}

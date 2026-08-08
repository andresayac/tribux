<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation;

use DOMDocument;
use InvalidArgumentException;

final class DianXsdValidator
{
    public function validate(string $xml, string $schemaPath): XmlValidationResult
    {
        if (! is_file($schemaPath)) {
            throw new InvalidArgumentException(sprintf('XSD schema not found: %s', $schemaPath));
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

            if (! $loaded) {
                return new XmlValidationResult(false, $this->collectErrors());
            }

            $valid = $document->schemaValidate($schemaPath);

            return new XmlValidationResult($valid, $this->collectErrors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    /** @return list<XmlValidationError> */
    private function collectErrors(): array
    {
        return array_map(
            static fn (\LibXMLError $error): XmlValidationError => XmlValidationError::fromLibxml($error),
            libxml_get_errors(),
        );
    }
}

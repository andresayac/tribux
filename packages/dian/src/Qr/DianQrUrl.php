<?php

declare(strict_types=1);

namespace Tribux\Dian\Qr;

use InvalidArgumentException;
use Tribux\Dian\DianEnvironment;

/**
 * Source: Anexo Tecnico FEV 1.9, section 11.7.1.
 */
final class DianQrUrl
{
    public function forDocumentKey(DianEnvironment $environment, string $documentKey): string
    {
        if (preg_match('/\A[a-f0-9]{96}\z/', $documentKey) !== 1) {
            throw new InvalidArgumentException('documentKey must be a lowercase SHA-384 hexadecimal digest.');
        }

        $baseUrl = match ($environment) {
            DianEnvironment::Habilitation => 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr',
            DianEnvironment::Production => 'https://catalogo-vpfe.dian.gov.co/document/searchqr',
        };

        return $baseUrl.'?documentkey='.$documentKey;
    }
}

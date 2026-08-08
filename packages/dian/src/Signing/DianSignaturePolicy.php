<?php

declare(strict_types=1);

namespace Tribux\Dian\Signing;

/**
 * DIAN signature policy v2 metadata observed in current FEV 1.9 examples.
 *
 * The policy PDF SHA-384 digest was independently recalculated from the
 * official URL on 2026-08-08. This value object does not sign XML.
 */
final readonly class DianSignaturePolicy
{
    private function __construct(
        public string $identifier,
        public string $digestMethod,
        public string $digestValue,
        public string $artifactSha256,
    ) {
    }

    public static function version2Sha384(): self
    {
        return new self(
            identifier: 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf',
            digestMethod: 'http://www.w3.org/2001/04/xmldsig-more#sha384',
            digestValue: 'EQC0kiWPaAME6IsEZ7WuaTWJ97Zmf6hIO69rMCVURmQxBB9ebgLrjhL5BArQ0a0l',
            artifactSha256: '74ca0cbed706e5a233818a34b48b1241e5490439d49df48e7c1a715eb9a8af46',
        );
    }

    public function matchesDocument(string $contents): bool
    {
        $actualDigest = base64_encode(hash('sha384', $contents, true));

        return hash_equals($this->digestValue, $actualDigest);
    }
}

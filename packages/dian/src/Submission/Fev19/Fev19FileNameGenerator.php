<?php

declare(strict_types=1);

namespace Tribux\Dian\Submission\Fev19;

use InvalidArgumentException;
use Tribux\Dian\Documents\DianDocumentType;

final readonly class Fev19FileNameGenerator
{
    private string $paddedIssuerTaxId;

    private string $yearSuffix;

    public function __construct(
        string $issuerTaxId,
        private string $providerCode,
        int $calendarYear,
    ) {
        if (preg_match('/\A[0-9]{1,10}\z/D', $issuerTaxId) !== 1) {
            throw new InvalidArgumentException('Issuer tax ID must contain between one and ten digits without check digit.');
        }

        if (preg_match('/\A[0-9]{3}\z/D', $providerCode) !== 1) {
            throw new InvalidArgumentException('DIAN provider code must contain exactly three digits.');
        }

        if ($calendarYear < 1000 || $calendarYear > 9999) {
            throw new InvalidArgumentException('Calendar year must contain four digits.');
        }

        $this->paddedIssuerTaxId = str_pad($issuerTaxId, 10, '0', STR_PAD_LEFT);
        $this->yearSuffix = substr((string) $calendarYear, -2);
    }

    public function documentName(DianDocumentType $type, Fev19FileSequence $sequence): string
    {
        return $type->filePrefix().$this->commonPart().$sequence->encoded.'.xml';
    }

    public function zipName(Fev19FileSequence $sequence): string
    {
        return 'z'.$this->commonPart().$sequence->encoded.'.zip';
    }

    private function commonPart(): string
    {
        return $this->paddedIssuerTaxId.$this->providerCode.$this->yearSuffix;
    }
}

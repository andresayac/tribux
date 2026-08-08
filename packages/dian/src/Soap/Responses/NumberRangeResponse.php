<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap\Responses;

use LogicException;
use SensitiveParameter;

/**
 * One authorized range as DIAN reports it.
 *
 * **This value carries a secret.** The WSDL declares TechnicalKey inside
 * NumberRangeResponse, so a numbering query answers with the resolution
 * technical key in plain text. It is kept private, redacted in debug output and
 * refused for serialization, and it must never reach a log line, an evidence
 * row or a job payload.
 *
 * Every field is nillable in the WSDL, so every field is nullable here. The
 * numbers are xs:long.
 */
final class NumberRangeResponse
{
    public function __construct(
        public readonly ?string $resolutionNumber,
        public readonly ?string $resolutionDate,
        public readonly ?string $prefix,
        public readonly ?int $fromNumber,
        public readonly ?int $toNumber,
        public readonly ?string $validDateFrom,
        public readonly ?string $validDateTo,
        #[SensitiveParameter]
        private readonly ?string $technicalKey = null,
    ) {}

    public function hasTechnicalKey(): bool
    {
        return $this->technicalKey !== null;
    }

    public function technicalKey(): ?string
    {
        return $this->technicalKey;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'resolutionNumber' => $this->resolutionNumber,
            'resolutionDate' => $this->resolutionDate,
            'prefix' => $this->prefix,
            'fromNumber' => $this->fromNumber,
            'toNumber' => $this->toNumber,
            'validDateFrom' => $this->validDateFrom,
            'validDateTo' => $this->validDateTo,
            'technicalKey' => $this->technicalKey === null ? null : '[redacted]',
        ];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new LogicException('A numbering range carries the technical key and must not be serialized.');
    }

    /** @return list<string> */
    public function __sleep(): array
    {
        throw new LogicException('A numbering range carries the technical key and must not be serialized.');
    }
}

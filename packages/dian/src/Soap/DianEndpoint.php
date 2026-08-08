<?php

declare(strict_types=1);

namespace Tribux\Dian\Soap;

use InvalidArgumentException;
use Tribux\Dian\DianEnvironment;

/**
 * A versioned DIAN service endpoint with an explicit override seam.
 *
 * DIAN may expose the operational URL through its participant catalogue, so
 * callers must be able to replace the observed defaults without changing code.
 */
final readonly class DianEndpoint
{
    public function __construct(
        public DianEnvironment $environment,
        public string $serviceUrl,
    ) {
        if (filter_var($serviceUrl, FILTER_VALIDATE_URL) === false || ! str_starts_with($serviceUrl, 'https://')) {
            throw new InvalidArgumentException('DIAN serviceUrl must be an absolute HTTPS URL.');
        }
    }

    public static function defaultFor(DianEnvironment $environment): self
    {
        return new self(
            environment: $environment,
            serviceUrl: match ($environment) {
                DianEnvironment::Habilitation => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                DianEnvironment::Production => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',
            },
        );
    }

    public function singleWsdlUrl(): string
    {
        return $this->serviceUrl.'?singleWsdl';
    }
}

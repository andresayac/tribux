<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Soap;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianSoapOperation;

final class DianEndpointTest extends TestCase
{
    #[Test]
    public function it_exposes_the_verified_default_endpoints(): void
    {
        $habilitation = DianEndpoint::defaultFor(DianEnvironment::Habilitation);
        $production = DianEndpoint::defaultFor(DianEnvironment::Production);

        self::assertSame(
            'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?singleWsdl',
            $habilitation->singleWsdlUrl(),
        );
        self::assertSame('https://vpfe.dian.gov.co/WcfDianCustomerServices.svc', $production->serviceUrl);
    }

    #[Test]
    public function it_rejects_an_insecure_endpoint_override(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DianEndpoint(DianEnvironment::Habilitation, 'http://example.test/service');
    }

    #[Test]
    public function it_builds_the_official_soap_action(): void
    {
        self::assertSame(
            'http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync',
            DianSoapOperation::SendTestSetAsync->action(),
        );
    }

    #[Test]
    public function it_maps_environment_codes_from_the_official_catalogue(): void
    {
        self::assertSame('1', DianEnvironment::Production->profileExecutionId());
        self::assertSame('2', DianEnvironment::Habilitation->profileExecutionId());
    }
}

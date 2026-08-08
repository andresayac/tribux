<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Signing;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Signing\DianSignaturePolicy;
use Tribux\Dian\Signing\DianSignerRole;

final class DianSignaturePolicyTest extends TestCase
{
    #[Test]
    public function it_exposes_the_verified_v2_sha384_policy(): void
    {
        $fixture = $this->fixture();
        $expected = $fixture['policy'];
        self::assertIsArray($expected);

        $policy = DianSignaturePolicy::version2Sha384();

        self::assertSame($expected['identifier'], $policy->identifier);
        self::assertSame($expected['digest_method'], $policy->digestMethod);
        self::assertSame($expected['digest_value'], $policy->digestValue);
        self::assertSame($expected['sha256'], $policy->artifactSha256);
        self::assertFalse($policy->matchesDocument('not the official policy document'));
    }

    #[Test]
    public function it_uses_the_two_roles_defined_by_the_policy(): void
    {
        $fixture = $this->fixture();

        self::assertSame($fixture['signer_roles'], array_column(DianSignerRole::cases(), 'value'));
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $json = file_get_contents(__DIR__.'/../Fixtures/fev-1.9/signing/policy-v2-sha384.json');
        self::assertIsString($json);

        try {
            $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($fixture);

        return $fixture;
    }
}

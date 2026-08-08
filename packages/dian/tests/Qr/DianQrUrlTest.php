<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Qr;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Qr\DianQrUrl;

final class DianQrUrlTest extends TestCase
{
    private const string KEY = 'a4fc014b42bb99dfe64c46aa21eb319800ddbe8e75d7ad94fb9f128ec6f3151c6e832c37bfe42240b930dc41b66cc61d';

    #[Test]
    public function it_selects_the_official_url_for_each_environment(): void
    {
        $url = new DianQrUrl();

        self::assertSame(
            'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='.self::KEY,
            $url->forDocumentKey(DianEnvironment::Habilitation, self::KEY),
        );
        self::assertSame(
            'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.self::KEY,
            $url->forDocumentKey(DianEnvironment::Production, self::KEY),
        );
    }

    #[Test]
    public function it_rejects_a_non_sha384_document_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DianQrUrl())->forDocumentKey(DianEnvironment::Habilitation, 'not-a-cufe');
    }
}

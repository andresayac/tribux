<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Validation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Validation\DianXsdValidator;

final class DianXsdValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_an_xml_that_matches_its_schema(): void
    {
        $result = (new DianXsdValidator())->validate(
            $this->fixture('simple-document-valid.xml'),
            $this->fixturePath('simple-document.xsd'),
        );

        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
    }

    #[Test]
    public function it_preserves_structured_libxml_errors(): void
    {
        $result = (new DianXsdValidator())->validate(
            $this->fixture('simple-document-invalid.xml'),
            $this->fixturePath('simple-document.xsd'),
        );

        self::assertFalse($result->valid);
        self::assertNotEmpty($result->errors);
        self::assertGreaterThan(0, $result->errors[0]->code);
        self::assertGreaterThan(0, $result->errors[0]->line);
        self::assertStringContainsString('decimal', $result->errors[0]->message);
    }

    #[Test]
    public function it_rejects_malformed_xml_without_network_loading(): void
    {
        $result = (new DianXsdValidator())->validate(
            '<Document><unclosed></Document>',
            $this->fixturePath('simple-document.xsd'),
        );

        self::assertFalse($result->valid);
        self::assertNotEmpty($result->errors);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents($this->fixturePath($name));
        self::assertIsString($contents);

        return $contents;
    }

    private function fixturePath(string $name): string
    {
        return __DIR__.'/../Fixtures/validation/'.$name;
    }
}

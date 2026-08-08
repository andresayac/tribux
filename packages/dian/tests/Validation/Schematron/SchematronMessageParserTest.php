<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Validation\Schematron;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Validation\Schematron\SchematronMessageParser;
use Tribux\Dian\Validation\Schematron\SchematronSeverity;

final class SchematronMessageParserTest extends TestCase
{
    #[Test]
    public function it_preserves_dian_severity_code_and_original_message(): void
    {
        $messages = (new SchematronMessageParser())->parse(implode("\n", [
            "Fatal: [FAD03]- (R) ProfileID no corresponde",
            "Fatal: [FAD09c]- Fecha fuera de rango",
            "Warning: [FAV05]- Unidad de medida inválida",
            "Saxon diagnostic without a DIAN code",
        ]));

        self::assertCount(4, $messages);
        self::assertSame(SchematronSeverity::Fatal, $messages[0]->severity);
        self::assertSame('FAD03', $messages[0]->ruleCode);
        self::assertSame('(R) ProfileID no corresponde', $messages[0]->message);
        self::assertSame('Fatal: [FAD03]- (R) ProfileID no corresponde', $messages[0]->original);
        self::assertSame('FAD09c', $messages[1]->ruleCode);
        self::assertSame(SchematronSeverity::Warning, $messages[2]->severity);
        self::assertSame(SchematronSeverity::Diagnostic, $messages[3]->severity);
        self::assertNull($messages[3]->ruleCode);
    }
}

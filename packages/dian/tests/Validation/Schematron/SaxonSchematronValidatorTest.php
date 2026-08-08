<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Validation\Schematron;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\Validation\Schematron\SaxonRuntime;
use Tribux\Dian\Validation\Schematron\SaxonSchematronValidator;
use Tribux\Dian\Validation\Schematron\SchematronSeverity;

final class SaxonSchematronValidatorTest extends TestCase
{
    #[Test]
    public function it_runs_the_official_xslt3_and_returns_structured_messages_when_available(): void
    {
        $saxonHome = getenv('TRIBUX_SAXON_HOME');
        $toolbox = getenv('TRIBUX_FEV19_TOOLBOX');

        if (! is_string($saxonHome) || $saxonHome === '' || ! is_string($toolbox) || $toolbox === '') {
            self::markTestSkipped('Set TRIBUX_SAXON_HOME and TRIBUX_FEV19_TOOLBOX for the XSLT 3.0 integration test.');
        }

        $example = $this->findUniqueFile($toolbox, 'ejemplificacionIBUA-3.xml');
        $xml = file_get_contents($example);
        self::assertIsString($xml);
        $runtime = new SaxonRuntime(
            javaBinary: 'java',
            saxonJar: $saxonHome.'/saxon-he-12.10.jar',
            dependencyJars: [
                $saxonHome.'/lib/xmlresolver-5.3.3.jar',
                $saxonHome.'/lib/xmlresolver-5.3.3-data.jar',
            ],
        );
        $result = (new SaxonSchematronValidator($runtime))->validate(
            $xml,
            Fev19ArtifactSet::discover($toolbox)->compiledSchematron,
        );

        self::assertFalse(
            $result->valid,
            json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'No result details.',
        );
        self::assertNotEmpty($result->messages);
        self::assertTrue($this->containsRule($result->messages, 'FAD03', SchematronSeverity::Fatal));
    }

    /** @param list<\Tribux\Dian\Validation\Schematron\SchematronMessage> $messages */
    private function containsRule(array $messages, string $code, SchematronSeverity $severity): bool
    {
        foreach ($messages as $message) {
            if ($message->ruleCode === $code && $message->severity === $severity) {
                return true;
            }
        }

        return false;
    }

    private function findUniqueFile(string $root, string $fileName): string
    {
        $matches = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getFilename() === $fileName) {
                $matches[] = $file->getPathname();
            }
        }

        self::assertCount(1, $matches);

        return $matches[0];
    }
}

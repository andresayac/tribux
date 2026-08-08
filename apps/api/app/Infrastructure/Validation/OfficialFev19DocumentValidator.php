<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

use App\Application\Invoices\Building\Contracts\Fev19DocumentValidator;
use RuntimeException;
use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Validation\DianXsdValidator;
use Tribux\Dian\Validation\Schematron\SaxonRuntime;
use Tribux\Dian\Validation\Schematron\SaxonSchematronValidator;
use Tribux\Dian\Validation\Schematron\SchematronValidationResult;
use Tribux\Dian\Validation\XmlValidationResult;

/**
 * Validates against the official FEV 1.9 toolbox and a local SaxonJ-HE.
 *
 * Artefacts are discovered lazily so an installation that never builds a
 * document does not need the toolbox mounted, and the jars are globbed rather
 * than named so a manifest bump does not silently break validation.
 */
final class OfficialFev19DocumentValidator implements Fev19DocumentValidator
{
    private ?Fev19ArtifactSet $artifacts = null;

    private ?SaxonSchematronValidator $schematron = null;

    public function __construct(
        private readonly ?string $toolboxPath,
        private readonly ?string $saxonHome,
        private readonly string $javaBinary = 'java',
        private readonly int $timeoutSeconds = 30,
        private readonly DianXsdValidator $xsd = new DianXsdValidator,
    ) {}

    public function validateSchema(string $xml, DianDocumentType $type): XmlValidationResult
    {
        return $this->xsd->validate($xml, $this->artifacts()->xsdFor($type));
    }

    public function validateRules(string $xml): SchematronValidationResult
    {
        return $this->schematron()->validate($xml, $this->artifacts()->compiledSchematron);
    }

    private function artifacts(): Fev19ArtifactSet
    {
        if ($this->toolboxPath === null || trim($this->toolboxPath) === '') {
            throw new RuntimeException(
                'The FEV 1.9 toolbox is not configured. Set TRIBUX_FEV19_TOOLBOX to the extracted toolbox directory.',
            );
        }

        return $this->artifacts ??= Fev19ArtifactSet::discover($this->toolboxPath);
    }

    private function schematron(): SaxonSchematronValidator
    {
        if ($this->schematron instanceof SaxonSchematronValidator) {
            return $this->schematron;
        }

        if ($this->saxonHome === null || trim($this->saxonHome) === '') {
            throw new RuntimeException(
                'Saxon is not configured. Set TRIBUX_SAXON_HOME to the extracted SaxonJ-HE directory.',
            );
        }

        $home = rtrim($this->saxonHome, '/\\');

        // The distribution also ships saxon-he-test and saxon-he-xqj jars, so
        // the processor is matched by a plain version suffix rather than a
        // wildcard.
        $saxonJars = array_values(array_filter(
            glob($home.DIRECTORY_SEPARATOR.'saxon-he-*.jar') ?: [],
            static fn (string $path): bool => preg_match('/\Asaxon-he-\d+(?:\.\d+)*\.jar\z/D', basename($path)) === 1,
        ));

        if (count($saxonJars) !== 1) {
            throw new RuntimeException(sprintf(
                'Expected exactly one SaxonJ-HE processor jar in "%s", found %d.',
                $home,
                count($saxonJars),
            ));
        }

        return $this->schematron = new SaxonSchematronValidator(new SaxonRuntime(
            javaBinary: $this->javaBinary,
            saxonJar: $saxonJars[0],
            dependencyJars: array_values(glob($home.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'*.jar') ?: []),
            timeoutSeconds: $this->timeoutSeconds,
        ));
    }
}

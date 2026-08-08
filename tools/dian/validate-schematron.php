<?php

declare(strict_types=1);

use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\Validation\Schematron\SaxonRuntime;
use Tribux\Dian\Validation\Schematron\SaxonSchematronValidator;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$repositoryRoot = dirname(__DIR__, 2);
$xmlPath = $argv[1] ?? null;
$toolboxRoot = $argv[2] ?? $repositoryRoot.'/var/dian/fev/1.9/toolbox';
$saxonHome = $argv[3] ?? $repositoryRoot.'/var/tools/saxon/12.10/dist';

if ($xmlPath === null) {
    fwrite(STDERR, 'Usage: php tools/dian/validate-schematron.php <xml> [toolbox-root] [saxon-home]'.PHP_EOL);
    exit(1);
}

try {
    $xml = file_get_contents($xmlPath);

    if ($xml === false) {
        throw new RuntimeException(sprintf('Cannot read XML: %s', $xmlPath));
    }

    $runtime = new SaxonRuntime(
        javaBinary: 'java',
        saxonJar: $saxonHome.'/saxon-he-12.10.jar',
        dependencyJars: [
            $saxonHome.'/lib/xmlresolver-5.3.3.jar',
            $saxonHome.'/lib/xmlresolver-5.3.3-data.jar',
        ],
    );
    $stylesheet = Fev19ArtifactSet::discover($toolboxRoot)->compiledSchematron;
    $result = (new SaxonSchematronValidator($runtime))->validate($xml, $stylesheet);

    fwrite(STDOUT, json_encode(
        $result->toArray(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ).PHP_EOL);
    exit($result->valid ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

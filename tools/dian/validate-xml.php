<?php

declare(strict_types=1);

use Tribux\Dian\Artifacts\Fev19ArtifactSet;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Validation\DianXsdValidator;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$repositoryRoot = dirname(__DIR__, 2);
$xmlPath = $argv[1] ?? null;
$documentType = $argv[2] ?? DianDocumentType::Invoice->value;
$toolboxRoot = $argv[3] ?? $repositoryRoot.'/var/dian/fev/1.9/toolbox';

if ($xmlPath === null) {
    fwrite(STDERR, "Usage: php tools/dian/validate-xml.php <xml> [document-type] [toolbox-root]".PHP_EOL);
    exit(1);
}

try {
    $xml = file_get_contents($xmlPath);

    if ($xml === false) {
        throw new RuntimeException(sprintf('Cannot read XML: %s', $xmlPath));
    }

    $type = DianDocumentType::from($documentType);
    $artifacts = Fev19ArtifactSet::discover($toolboxRoot);
    $result = (new DianXsdValidator())->validate($xml, $artifacts->xsdFor($type));

    fwrite(STDOUT, json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit($result->valid ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

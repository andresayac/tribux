<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

use DOMDocument;
use InvalidArgumentException;
use RuntimeException;

final readonly class SaxonSchematronValidator
{
    public function __construct(
        private SaxonRuntime $runtime,
        private SchematronMessageParser $messageParser = new SchematronMessageParser(),
    ) {
    }

    public function validate(string $xml, string $compiledStylesheet): SchematronValidationResult
    {
        $this->validateInput($xml, $compiledStylesheet);
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tribux-schematron-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0700)) {
            throw new RuntimeException('Could not create the Schematron temporary directory.');
        }

        $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.xml';
        $outputPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'result.xml';

        try {
            if (file_put_contents($sourcePath, $xml, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the Schematron source document.');
            }

            $processMessages = $this->execute($sourcePath, $compiledStylesheet, $outputPath);
            $principalOutput = is_file($outputPath) ? file_get_contents($outputPath) : '';

            if ($principalOutput === false) {
                throw new RuntimeException('Could not read the Schematron transformation output.');
            }

            if (trim($processMessages) === '' && trim($principalOutput) === '') {
                throw new RuntimeException('Saxon completed without transformation output or diagnostic messages.');
            }

            $rawMessages = $processMessages;

            if (str_contains($principalOutput, 'Fatal:') || str_contains($principalOutput, 'Warning:')) {
                $rawMessages .= "\n".$principalOutput;
            }

            $messages = $this->messageParser->parse($rawMessages);
            $valid = true;

            foreach ($messages as $message) {
                if ($message->severity === SchematronSeverity::Fatal) {
                    $valid = false;
                    break;
                }
            }

            return new SchematronValidationResult($valid, $messages);
        } finally {
            foreach ([$sourcePath, $outputPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($temporaryDirectory);
        }
    }

    private function validateInput(string $xml, string $compiledStylesheet): void
    {
        if (! is_file($compiledStylesheet)) {
            throw new InvalidArgumentException(sprintf('Compiled Schematron stylesheet not found: %s', $compiledStylesheet));
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new InvalidArgumentException('Schematron input must be well-formed XML without a DOCTYPE.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function execute(string $sourcePath, string $stylesheetPath, string $outputPath): string
    {
        $command = [
            $this->runtime->javaBinary,
            '-Dfile.encoding=UTF-8',
            '-cp',
            $this->runtime->classPath(),
            'net.sf.saxon.Transform',
            '-dtd:off',
            '-ext:off',
            '-quit:on',
            '-s:'.$sourcePath,
            '-xsl:'.$stylesheetPath,
            '-o:'.$outputPath,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the Saxon process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $standardOutput = '';
        $standardError = '';
        $exitCode = -1;
        $deadline = microtime(true) + $this->runtime->timeoutSeconds;

        try {
            while (true) {
                $standardOutput .= stream_get_contents($pipes[1]) ?: '';
                $standardError .= stream_get_contents($pipes[2]) ?: '';
                $status = proc_get_status($process);

                if (! $status['running'] && $exitCode < 0) {
                    $exitCode = $status['exitcode'];
                }

                if (! $status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException(sprintf(
                        'Saxon Schematron validation exceeded %d seconds.',
                        $this->runtime->timeoutSeconds,
                    ));
                }

                usleep(10_000);
            }

            stream_set_blocking($pipes[1], true);
            stream_set_blocking($pipes[2], true);
            $standardOutput .= stream_get_contents($pipes[1]) ?: '';
            $standardError .= stream_get_contents($pipes[2]) ?: '';
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closedExitCode = proc_close($process);

            if ($exitCode < 0) {
                $exitCode = $closedExitCode;
            }
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Saxon Schematron execution failed with exit code %d: %s',
                $exitCode,
                trim($standardError !== '' ? $standardError : $standardOutput),
            ));
        }

        return trim($standardError."\n".$standardOutput);
    }
}

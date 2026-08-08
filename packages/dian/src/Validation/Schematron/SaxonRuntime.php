<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

use InvalidArgumentException;

final readonly class SaxonRuntime
{
    /** @param list<string> $dependencyJars */
    public function __construct(
        public string $javaBinary,
        public string $saxonJar,
        public array $dependencyJars,
        public int $timeoutSeconds = 30,
    ) {
        if (trim($javaBinary) === '') {
            throw new InvalidArgumentException('javaBinary cannot be empty.');
        }

        foreach ([$saxonJar, ...$dependencyJars] as $jar) {
            if (! is_file($jar)) {
                throw new InvalidArgumentException(sprintf('Saxon runtime JAR not found: %s', $jar));
            }
        }

        if ($timeoutSeconds < 1 || $timeoutSeconds > 300) {
            throw new InvalidArgumentException('Saxon timeout must be between 1 and 300 seconds.');
        }
    }

    public function classPath(): string
    {
        return implode(PATH_SEPARATOR, [$this->saxonJar, ...$this->dependencyJars]);
    }
}

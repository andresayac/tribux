<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}

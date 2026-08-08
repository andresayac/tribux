<?php

declare(strict_types=1);

namespace App\Infrastructure\Identifiers;

use App\Application\Contracts\IdGenerator;
use Illuminate\Support\Str;

final class UuidV7Generator implements IdGenerator
{
    public function generate(): string
    {
        return Str::uuid7()->toString();
    }
}

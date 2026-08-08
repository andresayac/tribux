<?php

declare(strict_types=1);

namespace Tribux\Dian;

enum DianEnvironment: string
{
    case Habilitation = 'habilitation';
    case Production = 'production';
}

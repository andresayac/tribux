<?php

declare(strict_types=1);

namespace Tribux\Dian;

enum DianEnvironment: string
{
    case Habilitation = 'habilitation';
    case Production = 'production';

    /**
     * DIAN cbc:ProfileExecutionID / cbc:UUID@schemeID value.
     *
     * Source: FEV 1.9 toolbox, TipoAmbiente-2.1.gc.
     */
    public function profileExecutionId(): string
    {
        return match ($this) {
            self::Production => '1',
            self::Habilitation => '2',
        };
    }
}

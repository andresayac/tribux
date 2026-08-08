<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation\Schematron;

enum SchematronSeverity: string
{
    case Fatal = 'fatal';
    case Warning = 'warning';
    case Diagnostic = 'diagnostic';
}

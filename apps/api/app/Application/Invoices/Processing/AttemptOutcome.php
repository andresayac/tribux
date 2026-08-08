<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

enum AttemptOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}

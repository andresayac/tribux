<?php

declare(strict_types=1);

namespace App\Application\Invoices\Exceptions;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Idempotency-Key was already used with a different request payload.');
    }
}

<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceControl
{
    public function __construct(
        public string $authorization,
        public string $authorizationStartDate,
        public string $authorizationEndDate,
        public string $prefix,
        public string $from,
        public string $to,
    ) {
        Fev19Value::text($authorization, 'authorization');
        Fev19Value::date($authorizationStartDate, 'authorizationStartDate');
        Fev19Value::date($authorizationEndDate, 'authorizationEndDate');
        Fev19Value::code($prefix, 'prefix');
        Fev19Value::digits($from, 'from');
        Fev19Value::digits($to, 'to');

        if ($authorizationEndDate < $authorizationStartDate) {
            throw new InvalidArgumentException('authorizationEndDate cannot precede authorizationStartDate.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Application\Invoices\Processing\StatusChangeSource;
use Illuminate\Database\Eloquent\Model;
use Tribux\Core\Invoice\InvoiceStatus;

final class InvoiceStatusHistoryRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'invoice_status_history';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'attempt_id',
        'from_status',
        'to_status',
        'source',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => InvoiceStatus::class,
            'to_status' => InvoiceStatus::class,
            'source' => StatusChangeSource::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}

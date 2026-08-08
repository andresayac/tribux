<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Tribux\Core\Invoice\InvoiceStatus;

final class InvoiceRecord extends Model
{
    public $incrementing = false;

    protected $table = 'invoices';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'issuer_id',
        'number',
        'status',
        'payload',
        'cufe',
        'created_at',
        'updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

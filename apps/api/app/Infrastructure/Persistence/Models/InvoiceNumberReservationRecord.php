<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class InvoiceNumberReservationRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'invoice_number_reservations';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'issuer_id',
        'authorization_reference',
        'prefix',
        'ordinal',
        'value',
        'invoice_id',
        'reserved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'reserved_at' => 'immutable_datetime',
        ];
    }
}

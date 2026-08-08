<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Application\Invoices\Numbering\DocumentSequenceScope;
use Illuminate\Database\Eloquent\Model;

final class DocumentSequenceReservationRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'document_sequence_reservations';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'issuer_id',
        'scope',
        'calendar_year',
        'ordinal',
        'owner_id',
        'reserved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => DocumentSequenceScope::class,
            'calendar_year' => 'integer',
            'ordinal' => 'integer',
            'reserved_at' => 'immutable_datetime',
        ];
    }
}

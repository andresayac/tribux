<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class IdempotencyKeyRecord extends Model
{
    protected $table = 'idempotency_keys';

    /** @var list<string> */
    protected $fillable = [
        'issuer_id',
        'operation',
        'key',
        'request_hash',
        'invoice_id',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Application\Invoices\Processing\EvidenceKind;
use Illuminate\Database\Eloquent\Model;

final class InvoiceEvidenceRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'invoice_evidence';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'attempt_id',
        'kind',
        'storage_reference',
        'sha256',
        'bytes',
        'media_type',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}

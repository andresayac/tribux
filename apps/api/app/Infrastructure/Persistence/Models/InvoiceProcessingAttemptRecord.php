<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use App\Application\Invoices\Processing\AttemptOutcome;
use App\Application\Invoices\Processing\ProcessingErrorCategory;
use App\Application\Invoices\Processing\ProcessingStage;
use Illuminate\Database\Eloquent\Model;
use Tribux\Dian\DianEnvironment;

final class InvoiceProcessingAttemptRecord extends Model
{
    public $incrementing = false;

    protected $table = 'invoice_processing_attempts';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'attempt_number',
        'environment',
        'stage',
        'outcome',
        'operation',
        'zip_key',
        'last_http_status',
        'error_category',
        'error_code',
        'error_message',
        'dian_messages',
        'started_at',
        'finished_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'environment' => DianEnvironment::class,
            'stage' => ProcessingStage::class,
            'outcome' => AttemptOutcome::class,
            'error_category' => ProcessingErrorCategory::class,
            'last_http_status' => 'integer',
            'dian_messages' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}

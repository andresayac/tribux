<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Processing attempts, append-only status history and evidence metadata.
 *
 * See ADR 0016. Binary artefacts never live here: this schema only stores the
 * reference, digest and size needed to audit and retrieve them.
 */
return new class extends Migration
{
    private const string ACTIVE_ATTEMPT_INDEX = 'invoice_processing_attempts_active_unique';

    public function up(): void
    {
        Schema::create('invoice_processing_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->unsignedInteger('attempt_number');
            $table->string('environment', 20);
            $table->string('stage', 40);
            $table->string('outcome', 20)->nullable();
            $table->string('operation', 60)->nullable();
            $table->string('zip_key', 200)->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('error_category', 40)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('dian_messages')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->unique(['invoice_id', 'attempt_number'], 'invoice_attempt_number_unique');
            $table->index(['invoice_id', 'started_at'], 'invoice_attempt_timeline_index');
        });

        // At most one open attempt per invoice. This is the real duplicate-send
        // guard; the row lock taken when opening an attempt only removes the
        // read-then-write race. PostgreSQL and SQLite both support it, so fast
        // tests and production share the invariant.
        DB::statement(sprintf(
            'create unique index %s on invoice_processing_attempts (invoice_id) where finished_at is null',
            self::ACTIVE_ATTEMPT_INDEX,
        ));

        Schema::create('invoice_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('attempt_id')->nullable();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('source', 20);
            $table->timestampTz('occurred_at');

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('attempt_id')->references('id')->on('invoice_processing_attempts')->nullOnDelete();
            $table->index(['invoice_id', 'occurred_at'], 'invoice_status_history_timeline_index');
        });

        Schema::create('invoice_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('attempt_id')->nullable();
            $table->string('kind', 60);
            $table->string('storage_reference', 500);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('media_type', 150);
            $table->timestampTz('created_at');

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('attempt_id')->references('id')->on('invoice_processing_attempts')->nullOnDelete();
            $table->index(['invoice_id', 'kind'], 'invoice_evidence_kind_index');
            $table->index(['attempt_id'], 'invoice_evidence_attempt_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_evidence');
        Schema::dropIfExists('invoice_status_history');

        DB::statement('drop index if exists '.self::ACTIVE_ATTEMPT_INDEX);

        Schema::dropIfExists('invoice_processing_attempts');
    }
};

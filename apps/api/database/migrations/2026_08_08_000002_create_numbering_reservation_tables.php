<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledgers of consumed numbers and file sequences.
 *
 * A ledger rather than a counter column: the unique indexes make a duplicate
 * impossible on any engine, and a number stays consumed after a failed or
 * ambiguous submission because its row is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('issuer_id', 100);
            $table->string('authorization_reference', 100);
            $table->string('prefix', 50);
            $table->unsignedBigInteger('ordinal');
            $table->string('value', 100);
            $table->uuid('invoice_id');
            $table->timestampTz('reserved_at');

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            // No number is ever issued twice inside an authorization.
            $table->unique(
                ['issuer_id', 'authorization_reference', 'prefix', 'ordinal'],
                'invoice_number_unique',
            );

            // No invoice ever holds two numbers, which makes reservation idempotent.
            $table->unique('invoice_id', 'invoice_number_owner_unique');
        });

        Schema::create('document_sequence_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('issuer_id', 100);
            $table->string('scope', 20);
            $table->unsignedSmallInteger('calendar_year');
            $table->unsignedBigInteger('ordinal');
            $table->uuid('owner_id');
            $table->timestampTz('reserved_at');

            $table->unique(
                ['issuer_id', 'scope', 'calendar_year', 'ordinal'],
                'document_sequence_unique',
            );

            $table->unique(['issuer_id', 'scope', 'owner_id'], 'document_sequence_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequence_reservations');
        Schema::dropIfExists('invoice_number_reservations');
    }
};

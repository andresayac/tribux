<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('issuer_id', 100)->index();
            $table->string('number', 100)->nullable();
            $table->string('status', 40)->index();
            $table->json('payload');
            $table->string('cufe')->nullable()->unique();
            $table->timestampsTz();
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('issuer_id', 100);
            $table->string('operation', 50);
            $table->string('key', 200);
            $table->char('request_hash', 64);
            $table->uuid('invoice_id');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['issuer_id', 'operation', 'key'], 'idempotency_scope_unique');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('invoices');
    }
};

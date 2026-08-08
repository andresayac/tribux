<?php

declare(strict_types=1);

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn (): array => [
    'status' => 'ok',
    'service' => 'tribux-api',
]);

Route::prefix('v1')->group(function (): void {
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoiceId}/status', [InvoiceController::class, 'status'])->name('invoices.status');
});

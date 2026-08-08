<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\GetInvoice;
use App\Http\Requests\CreateInvoiceRequest;
use Illuminate\Http\JsonResponse;

final class InvoiceController extends Controller
{
    public function store(CreateInvoiceRequest $request, CreateInvoice $createInvoice): JsonResponse
    {
        $result = $createInvoice->execute($request->invoicePayload(), $request->idempotencyKey());
        $location = route('invoices.show', ['invoiceId' => $result->invoice->id], absolute: false);

        return response()
            ->json($result->invoice->toArray(), 202)
            ->header('Location', $location)
            ->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function show(string $invoiceId, GetInvoice $getInvoice): JsonResponse
    {
        return response()->json($getInvoice->execute($invoiceId)->toArray());
    }

    public function status(string $invoiceId, GetInvoice $getInvoice): JsonResponse
    {
        $invoice = $getInvoice->execute($invoiceId);

        return response()->json([
            'id' => $invoice->id,
            'status' => $invoice->status->value,
            'cufe' => $invoice->cufe,
        ]);
    }
}

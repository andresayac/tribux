<?php

use App\Application\Invoices\Exceptions\IdempotencyConflict;
use App\Application\Invoices\Exceptions\InvoiceNotFound;
use App\Http\Middleware\RequestId;
use App\Http\Problems\ProblemResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->is('health'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            $errors = [];
            foreach ($exception->errors() as $path => $messages) {
                foreach ($messages as $message) {
                    $errors[] = [
                        'source' => 'REQUEST',
                        'code' => 'VALIDATION_ERROR',
                        'message' => $message,
                        'path' => $path,
                    ];
                }
            }

            return ProblemResponse::make(
                $request,
                422,
                'REQUEST_VALIDATION_FAILED',
                'The request payload is invalid.',
                errors: $errors,
            );
        });

        $exceptions->render(fn (InvalidArgumentException $exception, Request $request) => ProblemResponse::make(
            $request,
            422,
            'DOMAIN_VALIDATION_FAILED',
            'The invoice violates a domain invariant.',
            $exception->getMessage(),
        ));

        $exceptions->render(fn (IdempotencyConflict $exception, Request $request) => ProblemResponse::make(
            $request,
            409,
            'IDEMPOTENCY_CONFLICT',
            'Idempotency key conflict.',
            $exception->getMessage(),
        ));

        $exceptions->render(fn (InvoiceNotFound $exception, Request $request) => ProblemResponse::make(
            $request,
            404,
            'INVOICE_NOT_FOUND',
            'Invoice not found.',
            $exception->getMessage(),
        ));
    })->create();

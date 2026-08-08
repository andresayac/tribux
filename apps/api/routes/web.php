<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => [
    'name' => 'Tribux API',
    'status' => 'foundation-pre-alpha',
    'contract' => '/openapi/openapi.yaml',
]);

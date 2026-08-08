<?php

declare(strict_types=1);

namespace App\Http\Problems;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProblemResponse
{
    /** @param list<array{source:string,code:string,message:string,path?:string}> $errors */
    public static function make(
        Request $request,
        int $status,
        string $code,
        string $title,
        ?string $detail = null,
        array $errors = [],
    ): JsonResponse {
        $body = [
            'type' => sprintf('https://docs.tribux.dev/problems/%s', strtolower(str_replace('_', '-', $code))),
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'trace_id' => (string) $request->attributes->get('request_id', ''),
        ];

        if ($detail !== null && $detail !== '') {
            $body['detail'] = $detail;
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json(
            $body,
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}

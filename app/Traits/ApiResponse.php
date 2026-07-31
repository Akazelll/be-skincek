<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, array $meta = [], int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : $meta,
        ], $statusCode);
    }

    protected function errorResponse(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = ['message' => $message];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}

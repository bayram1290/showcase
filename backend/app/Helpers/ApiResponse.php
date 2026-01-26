<?php

namespace App\Helpers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error($message = 'Error', $error_code = null, int $code = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $error_code,
        ], $code);
    }

    public static function unauthorized(string $message = 'Unauthorized', ?string $error_code = null, int $status = Response::HTTP_UNAUTHORIZED): JsonResponse
    {
        return self::error(
            $message,
            $error_code,
            $status
        );
    }

    public static function notFound(string $message = 'Resource not found', ?string $error_code = null, int $status = Response::HTTP_NOT_FOUND): JsonResponse
    {
        return self::error(
            $message,
            $error_code,
            $status
        );
    }

    public static function validation(string $message = 'Validation failed', ?array $errors = null, ?string $error_code = null, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'success'=> true,
            'message' => $message,
            'errors' => $errors,
            'error_code' => $error_code,
        ], $status);
    }
}
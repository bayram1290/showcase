<?php

namespace App\Helpers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    public static function success($data = null, string $message =  'Success', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Error response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @param int $status
     * @return JsonResponse
     */
    public static function error(string $message = 'Error', ?string $errorCode = null, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!$errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        return response()->json([$response], $status);
    }

    /**
     * Unauthorized (401) response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @param int $status
     * @return JsonResponse
     */
    public function unauthorized(string $message = 'Unauthorized', ?string $errorCode = null, int $status = Response::HTTP_UNAUTHORIZED): JsonResponse
    {
        return $this->error($message, $errorCode, $status);
    }

    /**
     * Validation error (422) response.
     *
     * @param string $message
     * @param array|null $errors
     * @param string|null $errorCode
     * @param int $status
     * @return JsonResponse
     */
    public static function validation(string $message = 'Validation failed', ?array $errors = null, ?string $errorCode = null, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json([$response], $status);
    }

    /**
     * Forbidden (403) response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @return JsonResponse
     */
    public function forbidden(string $message = 'Forbidden', ?string  $errorCode = null): JsonResponse
    {
        return self::error($message, $errorCode, Response::HTTP_FORBIDDEN);
    }

    /**
     * Conflict (409) response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @return JsonResponse
     */
    public static function conflict(string $message = 'Conflict', ?string $errorCode = null): JsonResponse
    {
        return self::error($message, $errorCode, Response::HTTP_CONFLICT);
    }
}
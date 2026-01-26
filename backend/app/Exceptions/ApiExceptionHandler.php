<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Log;
use Throwable;

class ApiExceptionHandler extends Exception
{
    public static function handle (Throwable $e, Request $request): JsonResponse
    {
        $status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response = [
            'success' => false,
            'message' => 'Something went wrong'
        ];

        switch (true) {
            case $e instanceof AuthenticationException:
                $status_code = Response::HTTP_UNAUTHORIZED;
                $response['message'] = 'Unauthenticated. Please provide valid credentials.';
                break;

            case $e instanceof ModelNotFoundException:
                $status_code = Response::HTTP_NOT_FOUND;
                $model = class_basename($e->getModel());
                $ids = $e->getIds();
                $idStr = is_array($ids) ? implode(', ', $ids) : $ids;
                $response['message'] = "No {$model} found with ID: {$idStr}";
                break;

            case $e instanceof ValidationException:
                $status_code = Response::HTTP_UNPROCESSABLE_ENTITY;
                $response['message'] = 'Validation failed';
                $response['errors'] = $e->errors();
                break;

            case $e instanceof NotFoundHttpException:
                $status_code = Response::HTTP_NOT_FOUND;
                $response['message'] = 'Endpoint not found';
                break;

            case $e instanceof MethodNotAllowedHttpException:
                $status_code = Response::HTTP_METHOD_NOT_ALLOWED;
                $response['message'] = 'Method not allowed for this endpoint';
                $response['allowed_methods'] = $e->getHeaders()['Allow'] ?? [];
                break;

            case $e instanceof ThrottleRequestsException:
                $status_code = Response::HTTP_TOO_MANY_REQUESTS;
                $response['message'] = 'Too many requests';
                $response['retry_after'] = $e->getHeaders()['Retry-After'] ?? 60;
                break;

            case $e instanceof HttpException:
                $status_code = $e->getStatusCode();
                $response['message'] = $e->getMessage() ?: Response::$statusTexts[$status_code] ?? 'HTTP Error';
                break;

            default:
                $response['message'] = $e->getMessage();
                break;
        }

        $debug_enabled = config('services.api.app_debug_mode') === true && config('services.api.app_env_mode') !== 'production';

        if ($debug_enabled) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        if (app()->environment('production') && $status_code >= 500) {
            unset($response['debug']);

            $response['message'] = 'Unexpected error occurred. Please try again later.';

            Log::error('API exception handler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return response()->json($response, $status_code);
    }
}

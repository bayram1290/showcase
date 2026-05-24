<?php

namespace App\Exceptions;


use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    public static function handle(Throwable $e, Request $request): JsonResponse
    {
        // if (!$request->is('api/*')) {
        //     throw $e;
        // }

        $status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response = [
            'success' => false,
            'message' => 'Something went wrong',
        ];

        match (true) {
            $e instanceof AuthenticationException => static::handleAuthentication($response, $status_code),
            $e instanceof ModelNotFoundException => static::handleModelNotFound($e, $response, $status_code),
            $e instanceof ValidationException => static::handleValidation($e, $response, $status_code),
            $e instanceof NotFoundHttpException => static::handleNotFound($response, $status_code),
            $e instanceof MethodNotAllowedHttpException => static::handleMethodNotAllowed($e,$response, $status_code),
            $e instanceof ThrottleRequestsException => static::handleThrottle($e, $response, $status_code),
            $e instanceof HttpException => static::handleHttpException($e, $response, $status_code),
            default => static::handleDefault($e, $response, $status_code)
        };

        if (config('app.debug')) {
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
            $response['message'] = 'Unexpected error occurred. Please, try again later.';

            Log::error('API exception handler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user() ? $request->user()->id : null
            ]);
        }

        return response()->json($response, $status_code);
    }

    private static function handleAuthentication(array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_UNAUTHORIZED;
        $response['message'] = 'Unauthenticated. Please, login with valid credentials.';
    }

    private static function handleModelNotFound(ModelNotFoundException $e, array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_NOT_FOUND;
        $model = class_basename($e->getModel());
        $ids = $e->getIds();
        $id_str = is_array($ids) ? implode(', ', $ids) : $ids;
        $response['message'] = "No {$model} found with ID: {$id_str}";
    }

    private static function handleValidation(ValidationException $e, array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
        $response['message'] = 'Validation failed.';
        $response['errors'] = $e->errors();
    }

    private static function handleNotFound(array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_NOT_FOUND;
        $response['message'] = 'Endpoint not found.';
    }

    private static function handleMethodNotAllowed(MethodNotAllowedHttpException $e,array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_METHOD_NOT_ALLOWED;
        $response['message'] = 'Method not allowed for this endpoint.';
        $response['allowed_methods'] = $e->getHeaders()['Allow'] ?? [];
    }

    private static function handleThrottle(ThrottleRequestsException $e, array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_TOO_MANY_REQUESTS;
        $response['message'] = 'Too many requests. Please, try again later.';
        $response['retry_after'] = $e->getHeaders()['Retry-After'] ?? 60;
    }

    private static function handleHttpException(HttpException $e, array &$response, int &$statusCode): void
    {
        $statusCode = $e->getStatusCode();
        $response['message'] = $e->getMessage() ? : Response::$statusTexts[$statusCode] ?? 'HTTP Error.';
    }

    private static function handleDefault(Throwable $e, array &$response, int &$statusCode): void
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response['message'] = 'Unexpected error occurred. Error: ' . PHP_EOL . $e->getMessage();
    }

}
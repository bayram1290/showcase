<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request and verify that the user has the required role.
     *
     * @param Request $request The HTTP request containing the user data.
     * @param Closure $next The next middleware in the chain.
     * @param string ...$roles The roles that are required to access the resource.
     * @return Response The JSON response containing the result of the verification.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::unauthorized('Unauthenticated. Please, log in first.', '401');
        }

        if (!in_array($user->role, $roles)) {
            return ApiResponse::forbidden('Insufficient permissions. You are not authorized to access this resource.', '403');
        }

        return $next($request);
    }
}

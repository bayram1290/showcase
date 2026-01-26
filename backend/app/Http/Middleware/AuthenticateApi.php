<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Log;

class AuthenticateApi extends Authenticate
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return \Illuminate\Http\JsonResponse | mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (empty($guards)) {
            $guards = config("helper.default_guards", ['sanctum']);
            // $guards = config(['sanctum']);
        }

        try {
            return parent::handle($request, $next, ...$guards);
        } catch (\Illuminate\Auth\AuthenticationException $e) {

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }

            throw $e; // throw error if only web routes (will trigger redirect)
        }
    }

    /**
     * Redirect the user to the authentication page if unauthenticated.
     * If the request is from an API route or expects JSON, do not redirect.
     * Instead, return null to allow the exception to be handled by the caller.
     *
     * @param \Illuminate\Http\Request $request (probably)
     * @return null|string
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return route('login'); //For web routes only
    }
}

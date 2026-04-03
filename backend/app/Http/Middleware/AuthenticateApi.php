<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class AuthenticateApi extends \Illuminate\Auth\Middleware\Authenticate
{

    /**
     * Redirect the user to the authentication page if unauthenticated.
     * If the request is from an API route or expects JSON, do not redirect.
     * Instead, return null to allow the exception to be handled by the caller.
     *
     * @param \Illuminate\Http\Request $request (probably)
     * @return null|string
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*')) {
            return null;
        }

        // For web routes, fall back to default (or your own login route)
        return route('login');
    }
}

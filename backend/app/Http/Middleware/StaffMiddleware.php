<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || ! $request->user() instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user. Please, login with valid staff credentials.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

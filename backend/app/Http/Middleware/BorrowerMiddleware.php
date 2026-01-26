<?php

namespace App\Http\Middleware;

use App\Models\Borrower;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BorrowerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user() instanceof Borrower) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user. Please, login with valid borrower credentials.',
            ]);
        }

        return $next($request);
    }
}

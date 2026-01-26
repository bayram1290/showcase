<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user. Please, login with valid credentials.',
            ]);
        }

        if ($user instanceof User) {
            if (! in_array($user->role, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions. You are not authorized to perform this action.',
                ], Response::HTTP_FORBIDDEN);
            }
        } else {
            return response()->json([
                'success'=> false,
                'message'=> 'Role-based access only available for staff only',
            ]);
        }

        return $next($request);
    }
}

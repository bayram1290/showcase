<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Requests\SystemLoginRequest;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
// use Spatie\Permission\Models\Permission;
// use \Symfony\Component\HttpFoundation\Response;

class SystemAuthController extends Controller
{
    public function login(SystemLoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('login', 'password'))) {
            return ApiResponse::error('Invalid credentials', 'AUTH_FAILED', 401);
        }

        $user = Auth::user();
        if (!$user->is_active) {
            return ApiResponse::error('Account is deactivated', 'ACCOUNT_INACTIVE', 403);
        }

        $user->recordLogin();
        $token = $user->createToken($request->device_name ?? 'system_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user->only(['login', 'first_name', 'last_name', 'role']),
            'token' => $token,
            'permissions' => (new PermissionService())->getPermissions($user),
        ], 'AUTH_SUCCESS', 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::success(null, 'LOGOUT SUCCESS', 200);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        return ApiResponse::success([
            'user' => $user->only(['login', 'first_name', 'last_name', 'role', 'department']),
            'permissions' => (new PermissionService())->getPermissions($user),
        ]);
    }
}

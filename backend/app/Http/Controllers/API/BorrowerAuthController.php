<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\BorrowerLoginRequest;
use App\Http\Requests\CreateBorrowerRequest;
use App\Models\Borrower;
use App\Services\BorrowerDashboardService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class BorrowerAuthController extends Controller
{
    public function register(CreateBorrowerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['password'] = bcrypt($validated['password']);
            $validated['is_active'] = true;
            $validated['is_blocked'] = false;
            $validated['failed_login_attempts'] = 0;

            $borrower = Borrower::create($validated);
            $borrower->sendEmailVerificationNotification();


            $token = $borrower->createToken($request->device_name ?? 'borrower_token')->plainTextToken;

            return ApiResponse::success([
                'user' => $borrower->only(['login', 'first_name', 'last_name']),
                'token' => $token,
            ], 'AUTH_REGISTER_SUCCESS', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 'AUTH_REGISTER_FAILED', 500);
        }
    }

    public function login(BorrowerLoginRequest $request): JsonResponse
    {
        $login_data = $request->only('login', 'password');
        $borrower = Borrower::where('login', $login_data['login'])
                    ->first();

        if (!$borrower || !Hash::check($login_data['password'], $borrower->password)) {
            if ($borrower) {
                $borrower->recordFailedLogin();
            }
            return ApiResponse::error('Invalid credentials', 'AUTH_FAILED', 401);
        }

        if (!$borrower->is_active || $borrower->is_blocked) {
            return ApiResponse::error('Account is not active', 'ACCOUNT_INACTIVE', 403);
        }

        if (!$borrower->email_verified_at) {
            return ApiResponse::error('Please, verify your email address first', 'ACCOUNT_NOT_VERIFIED', 403);
        }

        $borrower->recordLogin();
        $token = $borrower->createToken($request->device_name ?? 'borrower_token')->plainTextToken;

        return ApiResponse::success([
            'user' => $borrower->only(['login', 'first_name', 'last_name']),
            'token' => $token,
        ], 'AUTH_SUCCESS', 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::success(null, 'LOGOUT_SUCCESS', 200);
    }

    public function profile(Request $request): JsonResponse
    {
        Log::info('adasd');
        $borrower = $request->user();
        return ApiResponse::success([
            'user' => $borrower->only(['login', 'first_name', 'last_name', 'phone']),
            'financial_summary' => (new BorrowerDashboardService())->getFinancialSummary($borrower),
        ]);
    }
}

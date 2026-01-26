<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBorrowerRequest;
use App\Models\Borrower;
use App\Models\User;

use App\Services\MobileValidationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Log;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
    //     $validator = Validator::make($request->all(), [
    //         'login' => 'required|string|max:255|unique:users',
    //         'name' => 'required|string|max:255',
    //         'password' => 'required|string|min:8|confirmed',
    //         'phone' => 'required|string|unique:users',
    //         'date_of_birth' => 'required|date',
    //         'address' => 'required|string',
    //         'city' => 'required|string',
    //         'state' => 'required|string',
    //         'zip_code' => 'required|string',
    //         'country' => 'required|string',
    //         'monthly_income' => 'required|numeric|min:0',
    //         'employment_status' => 'required|string',
    //         'employer_name' => 'required|string',
    //         'employment_duration' => 'required|integer|min:0',
    //         'ssn' => 'required|string|unique:users'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation Error.',
    //             'errors' => $validator->errors()
    //         ], Response::HTTP_UNPROCESSABLE_ENTITY);
    //     }

    //     $user = User::create([
    //         'name' => $request->name,
    //         'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'phone' => $request->phone,
    //         'date_of_birth' => $request->date_of_birth,
    //         'address' => $request->address,
    //         'city' => $request->city,
    //         'state' => $request->state,
    //         'zip_code' => $request->zip_code,
    //         'country' => $request->country,
    //         'monthly_income' => $request->monthly_income,
    //         'employment_status' => $request->employment_status,
    //         'employer_name' => $request->employer_name,
    //         'employment_duration' => $request->employment_duration,
    //         'ssn' => $request->ssn,
    //         'role' => 'customer'
    //     ]);

    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()->json([
    //         'success'=> true,
    //         'message'=> 'Registration successful',
    //         'user' => $user,
    //         'token' => $token
    //     ], 201);

        return response()->json([
            'success' => false,
            'message'=> 'Under development',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    public function login(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!Auth::attempt($request->only('login', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::where('login', $request->login)->first();

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->is_locked) {
            return response()->json([
                'success'=> false,
                'message'=> 'Account is locked due to multiple failed login attempts'
                ], Response::HTTP_FORBIDDEN);
        }

        $user->recordLogin();
        $token = $user->createToken($request->device_name ?? 'auth_token')->plainTextToken;

        return response()->json([
            'success'=> true,
            'message'=> 'Login successful',
            'user' => $user->only(['id', 'login', 'email', 'first_name', 'last_name', 'role', 'department']),
            'token' => $token,
            'permissions' => $this->getPermissions($user)
        ], Response::HTTP_OK);

    }

    public function borrowerLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = Borrower::where('login', $request->login)->first();
        if (!$borrower || !Hash::check($request->password, $borrower->password)) {
            if ($borrower) {
                $borrower->recordFailedLogin();
            }

            return response()->json([
                'success'=> false,
                'message'=> 'Invalid credentials'
                ], Response::HTTP_UNAUTHORIZED);

        }

        if (!$borrower->is_active) {
            return response()->json([
                'success'=> false,
                'message'=> 'Account is deactivated'
            ], Response::HTTP_FORBIDDEN);
        }


        if ($borrower->is_locked) {
            return response()->json([
                'success'=> false,
                'message'=> 'Account is blocked. Please contact with customer support.'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$borrower->verified()) {
            return response()->json([
                'success'=> false,
                'message'=> 'Not verified yet. Please, wait for verification process (it can take up to 24 business hours).'
            ], Response::HTTP_FORBIDDEN);
        }

        $borrower->recordLogin();

        $token = $borrower->createToken($request->device_name ?? 'borrower_token')->plainTextToken;
        return response()->json([
            'success'=> true,
            'message'=> 'Login successful',
            'user' => $borrower->only(['login', 'email', 'first_name', 'last_name', 'is_verified']),
            'token' => $token
        ]);

    }

    public function registerBorrower(CreateBorrowerRequest $request): JsonResponse
    {
        /*
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|unique:borrowers,login',
            'password' => 'required|string|min:8|confirmed',
            'email' => 'required|email|unique:borrowers,email',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'phone' => 'required|string|unique:borrowers,phone',
            'date_of_birth' => 'required|date|before:-16 years',
            'address' => 'required|string',
            'city' => 'required|string',
            'region' => 'required|string',
            'country' => 'required|string',
            'postal_code' => 'nullable|string',
            'monthly_income' => 'required|numeric|min:0',
            'employment_status' => 'required|in:employed,self_employed,unemployed,retired,student',
            'employer_name' => 'required_if:employment_status,employed,self_employed',
            'employment_duration_months' => 'nullable|integer|min:0',
            'occupation' => 'nullable|string',
            'ssn' => 'required|string|unique:borrowers,ssn',
            'government_id' => 'nullable|string|unique:borrowers,government_id_number',
            'government_id_type' => 'required|in:passport, nic, drivers_license, eid',
            'total_debt' => 'nullable|numeric|min:0',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'marital_status' => 'nullable|string',
            'dependents' => 'nullable|integer|min:0',
            'preferred_contact_method' => 'nullable|in:email,phone,sms'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = Borrower::create([
            'login' => $request->login,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'date_of_birth' => $request->date_of_birth,
            'address' => $request->address,
            'city' => $request->city,
            'region' => $request->region,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'monthly_income' => $request->monthly_income,
            'employment_status' => $request->employment_status,
            'employer_name' => $request->employer_name,
            'employment_duration_months' => $request->employment_duration_months,
            'occupation' => $request->occupation,
            'ssn' => $request->ssn,
            'government_id_number' => $request->government_id_number,
            'government_id_type' => $request->government_id_type,
            'total_debt' => $request->total_debt ?? 0,
            'monthly_expenses' => $request->monthly_expenses ?? 0,
            'marital_status' => $request->marital_status,
            'dependents' => $request->dependents ?? 0,
            'preferred_contact_method' => $request->preferred_contact_method ?? 'email',
            'is_active' => true,
            'is_verified' => false
        ]);

        // $this->sendVerificationNotification($borrower);

        $token = $borrower->createToken($request->device_name ?? 'borrower_token')->plainTextToken;
        return response()->json([
            'success'=> true,
            'message'=> 'Registration successful. Account requires verification.',
            'user' => $borrower->only(['id', 'login', 'email', 'first_name', 'last_name', 'is_verified']),
            'token' => $token
        ], Response::HTTP_CREATED);
        */

        try {
            $validated = $request->validated();
            $validated['gender'] = $validated['gender'][0];
            Log::info(gettype($validated['gender']));
            $validated['phone'] = MobileValidationService::cleanMobile($validated['phone']);
            $validated['password'] = bcrypt($validated['password']);

            $validated['total_debt'] = 0;
            $validated['is_verified'] = false;
            $validated['is_active'] = true;
            $validated['is_blocked'] = false;
            $validated['failed_login_attempts'] = 0;
            $validated['date_of_birth'] = Carbon::parse($validated['date_of_birth'])->format('Y-m-d');

            $borrower = Borrower::create($validated);

            $token = $borrower->createToken($request->device_name ?? 'bank-borrower-api-token')->plainTextToken;

            Log::info('New borrower registered', [
                'borrower_id' => $borrower->id,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent() ??'',
                'device_name' => $request->device_name ?? '',
            ]);

            return response()->json([
                'success'=> true,
                'message' => 'Registration successful. Account requires verification.',
                'data' => [
                    'borrower' => [
                        'login' => $validated['login'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'],
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'is_verified' => $validated['is_verified'],
                    ],
                    'token' => $token,
                    'expires_in' => config('sanctum.expiration') ? : null,
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Borrower registration failed: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
                'input' => $request->except(['password', 'password_confirmation', 'ssn'])
            ]);

            return response()->json([
                'success'=> false,
                'message'=> 'Registration failed. Please try again.',
                'error' => config('services.api.app_debug_mode') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'token' => 'sometimes|string|size:64'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request'
            ],  Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if (!$request->user() || !$request->user()->currentAccessToken()) {
                return ApiResponse::error('No active session found', 'NO_ACTIVE_SESSION', 400);
            }

            $request->user()->currentAccessToken()->delete();

            return ApiResponse::success(
                null,
                'Logged out successfully'
            );

        } catch (\Exception $e) {
            Log::error('Logout failed: ' . $e->getMessage());

            // return success to user (maybe, token already be invalid)
            return ApiResponse::error(null, 'Already logged out');
        }
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $data = $user->only(['id', 'login', 'email', 'first_name', 'last_name', 'role', 'department', 'phone', 'employee_id', 'date_of_joining', 'last_login_at']);
            $data['permissions'] = $this->getPermissions($user);
        } else {
            $data = $user->only(['id', 'login', 'email', 'first_name', 'last_name', 'phone', 'date_of_birth', 'address', 'city', 'region', 'country', 'monthly_income', 'employment_status', 'is_verified', 'last_login_at']);
            $data['age'] = Carbon::parse($user->date_of_birth)->age;

            # Add those related fields
            // $data['debt_to_income_ratio'] = $user->debt_to_income_ratio;
            // $data['monthly_savings'] = $user->monthly_savings;
        }

        return response()->json([
            'success' => true,
            'user' => $data
        ]);
    }

    public function getPermissions(User $user): array
    {
        $permissions = [
            'view_dashboard' => true,
            'view_applications' => $user->canReviewApplications(),
            'review_applications' => $user->canReviewApplications(),
            'manage_loan_products' => $user->isAdmin(),
            'manage_user' => $user->isAdmin(),
            'manage_borrowers' => in_array($user->role, ['admin', 'loan_officer']),
            'view_reports' => in_array($user->role, ['admin', 'loan_officer']),
            'process_payments' => in_array($user->role, ['admin', 'loan_officer']),
            'verify_documents' => $user->canReviewApplications(),
        ];

        return $permissions;
    }
}
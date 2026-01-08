<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'login' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|unique:users',
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip_code' => 'required|string',
            'country' => 'required|string',
            'monthly_income' => 'required|numeric|min:0',
            'employment_status' => 'required|string',
            'employer_name' => 'required|string',
            'employment_duration' => 'required|integer|min:0',
            'ssn' => 'required|string|unique:users'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zip_code' => $request->zip_code,
            'country' => $request->country,
            'monthly_income' => $request->monthly_income,
            'employment_status' => $request->employment_status,
            'employer_name' => $request->employer_name,
            'employment_duration' => $request->employment_duration,
            'ssn' => $request->ssn,
            'role' => 'customer'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'=> true,
            'message'=> 'Registration successful',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('login', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('login', $request->login)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'=> true,
            'message'=> 'Login successful',
            'user' => $user,
            'token' => $token
        ], 200);

    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }
}
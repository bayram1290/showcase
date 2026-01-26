<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Rules\ValidMobileNumber;
use Log;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has("role")) {
            $query->where("role", $request->role);
        }

        if ($request->has("department")) {
            $query->where("department", 'like', "%{$request->department}%");
        }

        if ($request->has("is_active")) {
            $query->where("is_active", $request->is_active);
        }

        if ($request->has("search")) {
            $search = $request->search;

            $query->where(function($q) use ($search) {
                $q->where('login', 'like', "%{$search}%")
                  ->OrWhere('first_name', 'like', "%{$search}%")
                  ->OrWhere('last_name', 'like', "%{$search}%")
                  ->OrWhere('employee_id', 'like', "%{$search}%")
                  ->OrWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function update(Request $request, int $user_id): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:50',
            'last_name' => 'sometimes|required|string|max:50',
            'phone' => ['sometimes', 'nullable', 'string', new ValidMobileNumber()],
            'role' => 'sometimes|required|in:loan_officer,moderator',
            'email' => 'sometimes|required|email|unique:users,email,' . $user_id,
            'department' => 'sometimes|nullable|string',
            'employee_id' => 'sometimes|nullable|string|unique:users,employee_id,' . $user_id,
            'date_of_joining' => 'sometimes|nullable|date',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors(),
            ],  Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::findOrFail($user_id);

        $update_data = $request->only([
            'first_name',
            'last_name',
            'phone',
            'role',
            'email',
            'department',
            'employee_id',
            'date_of_joining',
        ]);

        if ($request->has('password')) {
            $update_data['password'] = bcrypt($request->input('password'));
            $update_data['password_changed_at'] = Carbon::now();
        }

        $user->update($update_data);

        return response()->json([
            'success'=> true,
            'message' => 'User updated successfully',
            'data' => $user
        ], Response::HTTP_OK);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (auth()->user()->role != 'admin') {
            return response()->json([
                'success'=> false,
                'errors' => 'You do NOT have permission to perform this action'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success'=> false,
                'message'=> 'You CAN NOT delete your own account'
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$user->trashed()) {
            return response()->json([
                'success'=> false,
                'errors'=> 'User already deleted'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }


    public function activate(int $user_id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($user_id);

        if (auth()->user()->role != 'admin') {
            return response()->json([
                'success'=> false,
                'message'=> 'You do NOT have permission to perform this action'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success'=> false,
                'message'=> 'You CAN NOT activate your own account'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->is_active && !$user->trashed()) {
            return response()->json([
                'success'=> false,
                'message'=> 'User already activated'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->update([
            'is_active' => true,
            'is_locked' => false,
            'failed_login_attempts' => 0,
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        return response()->json([
            'success'=> true,
            'message' => 'User activated successfully',
            'data'=> $user
        ], Response::HTTP_OK);
    }

    public function deactivate(int $user_id): JsonResponse
    {
        $user = User::findOrFail($user_id);

        if ($user->is_active === false || $user->trashed()) {
            return response()->json([
                'success'=> false,
                'message'=> 'User already deactivated/deleted'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->id === auth()->id() && $user->trashed()) {
            return response()->json([
                'success'=> false,
                'message'=> 'You CAN NOT deactivate your own account'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success'=> false,
                'message'=> 'You CAN NOT deactivate admin account'
            ], Response::HTTP_FORBIDDEN);
        }

        if (auth()->user()->role != 'admin') {
            return response()->json([
                'success'=> false,
                'message'=> 'You do NOT have permission to perform this action'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->update([
            'is_active' => false,
            'is_locked' => false,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'success'=> true,
            'message'=> 'User deactivated successfully',
        ], Response::HTTP_OK);
    }
}

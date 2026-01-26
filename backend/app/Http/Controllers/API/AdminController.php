<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function getUsers(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->get('role'));
        }

        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->get('is_verified'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('login', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:customer,loan_officer,admin',
            'is_verified' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($id);
        $update_data = ['role' => $request->role];

        if ($request->has('is_verified')) {
            $update_data['is_verified'] = $request->get('is_verified');
        }

        $user->update($update_data);

        return response()->json([
            'success'=> true,
            'message' => 'User role updated successfully',
            'data' => $user
        ]);
    }

    public function getUser(int $id): JsonResponse
    {
        $user = User::withCount([
            'loanApplications',
            'loanApplications as pending_applications' => function($q): void {
                $q->whereIn('status', ['submitted', 'under_review']);
            },
            'loanApplications as approved_applications' => function($q): void {
                $q->where('status', 'approved');
            }
        ])->findOrFail($id);

        return response()->json([
            'success'=> true,
            'data' => $user
        ]);
    }

    public function systemStats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_officers' => User::where('role', 'loan_officer')->count(),
            'total_applications' => LoanApplication::count(),
            'total_loan_disbursed' => LoanAccount::count(),
            'total_amount_disbursed' => LoanAccount::sum('disbursed_amount'),
            'total_outstanding' => LoanAccount::sum('outstanding_balance'),
            'active_loans' => LoanAccount::active()->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}

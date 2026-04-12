<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class BorrowerController extends Controller
{
    /**
     * List of borrowers filtered by the request parameters, paginated by default.
     * Parameters:
     *   - email_verified_at: Filter by whether the borrower has verified their email address
     *   - is_active: Filter by whether the borrower is active or not
     *   - is_blocked: Filter by whether the borrower is blocked or not
     *   - employment_status: Filter by the borrower's employment status
     *   - region: Filter by the borrower's region
     *   - city: Filter by the borrower's city
     *   - min_income: Filter by the borrower's minimum income
     *   - max_income: Filter by the borrower's maximum income
     *   - search: Search for a borrower based on:
     *      * login
     *      * first name or,
     *      * last name or,
     *      * phone or,
     *      * SSN or,
     *      * email
     *   sort_by: The field to sort the results by
     *   sort_order: The order of the sorting (asc or desc)
     *   per_page: The number of results to return per page
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Borrower::query();

        if ($request->has("email_verified_at")) {
            $query = $query->where("email_verified_at", $request->email_verified_at);
        }

        if ($request->has("is_active")) {
            $query = $query->where("is_active", $request->is_active);
        }

        if ($request->has("is_blocked")) {
            $query = $query->where("is_blocked", $request->is_blocked);
        }

        if ($request->has("employment_status")) {
            $query = $query->where("employment_status", $request->employment_status);
        }

        if ($request->has("region")) {
            $query = $query->where("region", $request->region);
        }

        if ($request->has("city")) {
            $query = $query->where("city", $request->city);
        }

        if ($request->has("min_income")) {
            $query = $query->where("monthly_income", '>=',  $request->min_income);
        }

        if ($request->has("max_income")) {
            $query = $query->where("monthly_income", "<=", $request->max_income);
        }

        if ($request->has("search")) {
            $search = $request->search;

            $query = $query->where(function ($q) use ($search): void {
                $q->where("login", "like", "%{$search}%")
                  ->orWhere("first_name", "like", "%{$search}%")
                  ->orWhere("last_name", "like", "%{$search}%")
                  ->orWhere("phone", "like", "%{$search}%")
                  ->orWhere("ssn", "like", "%{$search}%")
                  ->orWhere("email", "like", "%{$search}%");
            });
        }

        $sort_by = $request->input('sort_by', 'created_at');
        $sort_order = $request->input('sort_order','desc');
        $query->orderBy($sort_by, $sort_order);

        $borrowers = $query->paginate($request->input('per_page', config('helper.default_pagination_length')));

        $statistics = [
            'total' => Borrower::count(),
            'verified' => Borrower::where('email_verified_at', true)->count(),
            'active' => Borrower::where('is_active', true)->count(),
            'blocked' => Borrower::where('is_blocked', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $borrowers,
            'statistics' => $statistics
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $borrower_id): JsonResponse
    {
        $borrower = Borrower::with([
            'loanApplications' => function ($query) {
                $query->orderByDesc('created_at')
                ->limit(10);
            },
            'loanApplications.loanProduct',
            'loanApplications.assignedOfficer',
        ])->findOrFail(intval($borrower_id));

        $financial_summary = [
            'total_applications' => $borrower->loanApplications->count(),
            'approved_applications' => $borrower->loanApplications->where('status', 'approved')->count(),
            'active_loans' => $borrower->loanAccounts()->active()->count() ?? 0,
            'total_borroweds' => $borrower->loanAccounts->sum('disbursed_amount'),
            'outstanding_balance' => $borrower->loanAccounts->sum('outstanding_balance'),
            'debt_to_income_ratio' => $borrower->total_debt > 0 ? round(($borrower->total_debt / $borrower->monthly_income) * 100, 2) : 0,
            'monthly_savings' => max(0, $borrower->monthly_income - $borrower->monthly_expenses),
        ];

        return response()->json([
            'success' => true,
            'data' => $borrower,
            'financial_summary' => $financial_summary
        ]);
    }

    /**
     * Block a borrower
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function block(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = Borrower::findOrFail($id);

        if ($borrower->is_blocked) {
            return response()->json([
                'success'=> false,
                'errors'=> 'Borrower is already blocked',
            ], Response::HTTP_BAD_REQUEST);
        }

        AuditLog::create([
            'action' => 'Borrower is blocked',
            'old_data' => [
                'borrower_id' => $borrower->id,
                'is_active' => $borrower->is_active ? 'Yes':'No',
                'is_blocked' => $borrower->is_blocked ? 'Yes':'No',
            ],
            'new_data' => [
                'borrower_id' => $borrower->id,
                'is_active' => 'No',
                'is_blocked' => 'Yes',
                'blocked_for_reason' => $request->reason,
                'blocked_at' => Carbon::now(),
            ],
            'user_id' => auth()->user()->id,
        ]);

        $borrower->update([
            'is_blocked' => true,
            'is_active' => false,
        ]);

        $borrower->tokens()->delete();

        return response()->json([
            'success'=> true,
            'message'=> 'Borrower blocked successfully',
            'data' => $borrower
        ], Response::HTTP_OK);
    }

    /**
     * Unblock a borrower
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblock(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = Borrower::findOrFail($id);

        if (!$borrower->is_blocked) {
            return response()->json([
                'success'=> false,
                'errors'=> 'Borrower is not blocked',
            ], Response::HTTP_BAD_REQUEST);
        }

        AuditLog::create([
            'action' => 'Borrower is unblocked',
            'old_data' => [
                'borrower_id' => $borrower->id,
                'is_active' => $borrower->is_active ? 'Yes':'No',
                'is_blocked' => $borrower->is_blocked ? 'Yes':'No',
            ],
            'new_data' => [
                'borrower_id' => $borrower->id,
                'is_active' => 'Yes',
                'is_blocked' => 'No',
                'blocked_for_reason' => $request->reason,
                'blocked_at' => Carbon::now(),
            ],
            'user_id' => auth()->user()->id,
        ]);

        $borrower->update([
            'is_blocked' => false,
            'is_active' => true,
            'failed_login_attempts' => 0
        ]);

        return response()->json([
            'success'=> true,
            'message' => 'Borrower unblocked successfully',
            'data' => $borrower
        ], Response::HTTP_OK);

    }
}

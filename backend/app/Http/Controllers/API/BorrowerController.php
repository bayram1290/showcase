<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class BorrowerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Borrower::query();

        if ($request->has("is_verified")) {
            $query = $query->where("is_verified", $request->is_verified);
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

        $sort_by = $request->get('sort_by', 'created_at');
        $sort_order = $request->get('sort_order','desc');
        $query->orderBy($sort_by, $sort_order);

        $borrowers = $query->paginate($request->get('per_page', 15));

        $statistics = [
            'total' => Borrower::count(),
            'verified' => Borrower::where('is_verified', true)->count(),
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
     * Verify a borrower
     *
     * @param \Illuminate\Http\Request  $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = Borrower::findOrFail($id);

        if ($borrower->is_verified) {
            return response()->json([
                'success'=> false,
                'errors'=> 'Borrower is already verified',
            ], Response::HTTP_BAD_REQUEST);
        }
        AuditLog::create([
            'action' => 'Borrower verified',
            'old_data' => [
                'borrower_id' => $borrower->id,
                'is_verified' => $borrower->is_verified ? 'Yes':'No',
                'verified_at' => $borrower->verified_at,
            ],
            'new_data' => [
                'borrower_id' => $borrower->id,
                'is_verified' => 'Yes',
                'verified_at' => Carbon::now(),
                'notes' => $request->notes
            ],
            'user_id' => auth()->user()->id,
        ]);

        $borrower->verify();

        return response()->json([
            'success' => true,
            'message' => 'Borrower verified successfully',
            'data' => $borrower
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

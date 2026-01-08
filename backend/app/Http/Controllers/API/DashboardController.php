<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $sunctum = ['auth:sanctum'];

    public function __construct() {
        $this->middleware($this->sunctum);
    }

    public function customerStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'loan_application_count' => LoanApplication::where('user_id', $user->id)->count(),
            'pending_application_count'=> LoanApplication::where('user_id', $user->id)
                ->whereIn('status', ['draft', 'submitted', 'under_review'])->count(),
            'approved_application_count'=> LoanApplication::where('user_id', $user->id)
                ->where('status', 'approved')->count(),
            'active_loan_count' => LoanApplication::whereHas('loanApplication', function ($q) use ($user): void {
                    $q->where('user_id', $user->id);
                })->where('status', 'approved')->count(),
            'total_borrowed' => LoanApplication::whereHas('loanApplication', function ($q) use ($user): void {
                    $q->where('user_id', $user->id);
                })->sum('disbursed_amount'),
            'outstanding_balance' => LoanApplication::whereHas('loanApplication', function ($q) use ($user): void {
                    $q->where('user_id', $user->id);
                })->sum('outstanding_balance'),
            'next_payment' => Installment::whereHas('loanAccount.loanApplication', function($q) use ($user): void {
                    $q->where('user_id', $user->id);
                })->where('status', 'pending')->where('due_date', '>=', today())->orderBy('created_at','desc')->first()
        ];

        $recent_applications = LoanApplication::with('loanProduct')
            ->where('user_id', $user->id)
            ->orderBy('created_at','desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_applications' => $recent_applications
        ]);

    }

    public function adminStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isLoanOfficer() || !$user->isAdmin()) {
            return response()->json([
                'success'=> false,
                'message'=> 'Unauthorized access'
            ], 403);
        }

        $application_stats = DB::table('loan_applications')
            ->select(
                DB::raw('COUNT * as total'),
                DB::raw('SUM(CASE WHEN status == "submitted" THEN 1 ELSE 0 END) as submitted'),
                DB::raw('SUM(CASE WHEN status == "under_review" THEN 1 ELSE 0 END) as under_review'),
                DB::raw('SUM(CASE WHEN status == "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status == "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN status == "disbursed" THEN 1 ELSE 0 END) as disbursed')
            )
            ->first();

        $loan_stats = DB::table('loan_accounts')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(disbursed_amount) as total_disbursed'),
                DB::raw('SUM(outstanding_balance) as total_outstanding'),
                DB::raw('SUM(CASE WHEN status == "active" THEN 1 ELSE 0 END) as active'),
                DB::raw('SUM(CASE WHEN status == "closed" THEN 1 ELSE 0 END) as closed'),
                DB::raw('SUM(CASE WHEN status == "defaulted" THEN 1 ELSE 0 END) as defaulted')
            )->first();

        $payment_stats = DB::table('installments')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue'),
                DB::raw('SUM(paid_amount) as total_paid')
            )
            ->first();

        $user_stats = DB::table('users')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN role = "customer" THEN 1 ELSE 0 END) as customers'),
                DB::raw('SUM(CASE WHEN role = "loan_officer" THEN 1 ELSE 0 END) as loan_officers'),
                DB::raw('SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) as admins'),
            )
            ->first();

        $recent_ten_applications = LoanApplication::with(['user', 'loanProduct', 'assignOfficer'])
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $upcoming_ten_applications = LoanApplication::with(['loanAccount.loanApplication.user'])
            ->where('status', 'pending')
            ->where('due_date', '<=', today()->addDays(7))
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        $loan_product_stats = DB::table('loan_applications as la')
            ->join('loan_products as lp', 'la.loan_product_id', '=', 'lp.id')
            ->select(
                'lp.name',
                DB::raw('COUNT(lp.id) as count'),
                DB::raw('SUM(la.amount) as total_amount')
            )
            ->groupBy('lp.id', 'lp.name')
            ->get();

        $stats = [
            'applications' => $application_stats,
            'loans' => $loan_stats,
            'payments' => $payment_stats,
            'users' => $user_stats,
            'loan_product_distribution' => $loan_product_stats
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_applications' => $recent_ten_applications,
            'upcoming_payments' => $upcoming_ten_applications
        ]);
    }

    public function monthlyTrend(Request $request): JsonResponse
    {

        $user = $request->user();

        if (!$user->isLoanOfficer() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $trend = DB::table('loan_applications')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as applications'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('created_at', '>=', now()->subMonth(12))
            ->groupBy('month')
            ->orderBy('month','asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trend
        ]);
    }
}

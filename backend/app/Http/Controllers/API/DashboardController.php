<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{

    /**
     * Return dashboard statistics for a borrower.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function borrowerStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get application statistics
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

        // Get the recent loan applications for the borrower.
        $recent_applications = LoanApplication::with('loanProduct')
            ->where('user_id', $user->id)
            ->orderBy('created_at','desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_applications' => $recent_applications
        ], Response::HTTP_OK);
    }
    /**
     * Return dashboard statistics for loan officer/admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isLoanOfficer() && !$user->isAdmin()) {
            return response()->json([
                'success'=> false,
                'message'=> 'Unauthorized access'
            ], 403);
        }

        $loan_officer = $user->isLoanOfficer();
        $officer_id = $loan_officer ? $user->id : null;

        $application_query = DB::table('loan_applications');

        if ($loan_officer) {
            $application_query->where('assigned_officer_id', $officer_id);
        }

        $application_stats = $application_query->select(
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "submitted" THEN 1 ELSE 0 END) as submitted'),
            DB::raw('SUM(CASE WHEN status = "under_review" THEN 1 ELSE 0 END) as under_review'),
            DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
            DB::raw('SUM(CASE WHEN status = "disbursed" THEN 1 ELSE 0 END) as disbursed')
        )->first();

        $account_query = DB::table('loan_accounts AS l_acc')
                        ->join('loan_applications AS l_app', 'l_acc.loan_application_id', '=', 'l_app.id');

        if ($loan_officer) {
            $account_query->where('l_app.assigned_officer_id', $officer_id);
        }

        $loan_stats = $account_query->select(
                DB::raw('COUNT(l_acc.id) as total'),
                DB::raw('SUM(l_acc.disbursed_amount) as total_disbursed'),
                DB::raw('SUM(l_acc.outstanding_balance) as total_outstanding'),
                DB::raw('SUM(CASE WHEN l_acc.status = "active" THEN 1 ELSE 0 END) as active'),
                DB::raw('SUM(CASE WHEN l_acc.status = "closed" THEN 1 ELSE 0 END) as closed'),
                DB::raw('SUM(CASE WHEN l_acc.status = "defaulted" THEN 1 ELSE 0 END) as defaulted')
        )->first();

        $payment_query = DB::table('installments AS i')
                        ->join('loan_accounts AS l_acc', 'i.loan_account_id', '=', 'l_acc.id')
                        ->join('loan_applications AS l_app', 'l_acc.loan_application_id', '=', 'l_app.id');

        if ($loan_officer) {
            $payment_query->where('l_app.assigned_officer_id', $officer_id);
        }

        $payment_stats = $payment_query->select(
            DB::raw('COUNT(i.id) as total'),
            DB::raw('SUM(CASE WHEN i.status = "paid" THEN 1 ELSE 0 END) as paid'),
            DB::raw('SUM(CASE WHEN i.status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN i.status = "overdue" THEN 1 ELSE 0 END) as overdue'),
            DB::raw('SUM(CASE WHEN i.status = "partial" THEN 1 ELSE 0 END) as partial'),
            DB::raw('SUM(i.paid_amount) as total_paid')
        )->first();

        $user_stats = null;
        if (!$loan_officer) {
            $user_stats = DB::table('users')->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN role = "customer" THEN 1 ELSE 0 END) as customers'),
                DB::raw('SUM(CASE WHEN role = "loan_officer" THEN 1 ELSE 0 END) as loan_officers'),
                DB::raw('SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) as admins')
            )->first();
        }

        $recent_application_query = LoanApplication::with(['borrower', 'loanProduct', 'assignedOfficer']);

        if ($loan_officer) {
            $recent_application_query->where('assigned_officer_id', $officer_id);
        }

        $recent_applications = $recent_application_query
                                ->whereIn('status', ['submitted', 'under_review'])
                                ->orderByDesc('created_at')
                                ->limit(config('helper.default_query_get_limit'))
                                ->get();

        $upcoming_installment_query = Installment::with(['loanAccount.loanApplication.borrower', 'loanAccount.loanApplication.assignedOfficer']);

        if ($loan_officer) {
            $upcoming_installment_query->whereHas('loanAccount.loanApplication', function ($q) use ($officer_id): void {
                $q->where('assigned_officer_id', $officer_id);
            });
        }

        $upcoming_installments = $upcoming_installment_query
                                ->where('status', '=', 'pending')
                                ->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(7)])
                                ->orderByDesc('due_date')
                                ->limit(config('helper.default_query_get_limit'))
                                ->get();

        $loan_product_query = DB::table('loan_applications AS l_app')
                                ->join('loan_products AS l_prod', 'l_app.loan_product_id', '=', 'l_prod.id');

        if ($loan_officer) {
            $loan_product_query->where('l_app.assigned_officer_id', $officer_id);
        }

        $loan_products = $loan_product_query
                            ->select(
                                'l_prod.name',
                                DB::raw('COUNT(l_app.id) as count'),
                                DB::raw('SUM(l_app.amount) as total_amount'),
                            )
                            ->groupBy('l_prod.id', 'l_prod.name')
                            ->get();

        $overdue_payment_query = Installment::where(function ($query) {
            $query->where('status', 'overdue')
                ->orWhere(function ($q) {
                    $q->where('status','pending')
                        ->where('due_date', '<', Carbon::today());
                });
        });

        if ($loan_officer) {
            $loan_product_query->whereHas('loanAccount.loanApplication',  function ($query) use ($officer_id) {
                    $query->where('assigned_officer_id', $officer_id);
            });
        }

        $overdue_payments = $overdue_payment_query->count();

        $recent_disburment_query = LoanAccount::with(['loanApplication.borrower', 'loanApplication.assignedOfficer']);

        if ($loan_officer) {
            $recent_disburment_query->whereHas('loanApplication', function ($q) use ($officer_id): void {
                $q->where('assigned_officer_id', $officer_id);
            });
        }

        $recent_disburments = $recent_disburment_query
                                ->whereDate('created_at', '>=', Carbon::today()->subDays(30))
                                ->orderByDesc('created_at')
                                ->limit(config('helper.default_query_get_limit'))
                                ->get();

        $officer_stats = null;
        if ($loan_officer) {
            $pending_reviews = LoanApplication::where('assigned_officer_id', $officer_id)
                                ->whereIn('status', ['under_review', 'submitted'])
                                ->count();

            $officer_stats = [
                'pending_reviews' => $pending_reviews,
                'assigned_applications' => $application_stats->total ?? 0,
                'assigned_active_loans' => $loan_stats->active ?? 0
            ];
        }

        $statistics = [
            'applications' => $application_stats,
            'loans' => $loan_stats,
            'payments' => $payment_stats,
            'users' => $user_stats,
            'loan_product_distribution' => $loan_products,
            'overdue_payments' => $overdue_payments,
        ];

        if ($loan_officer) {
            $statistics['officer'] = $officer_stats;
        }

        return response()->json([
            'success' => true,
            'user_role' => $loan_officer ? 'loan_officer' : 'admin',
            'stats' => $statistics,
            'recent_applications' => $recent_applications,
            'upcoming_payments' => $upcoming_installments,
            'recent_disburments' => $recent_disburments
        ], Response::HTTP_OK);

    }

    public function monthlyTrend(Request $request): JsonResponse
    {

        $user = $request->user();

        if (!$user->isLoanOfficer() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], Response::HTTP_FORBIDDEN);
        }

        $loan_officer = $user->isLoanOfficer();
        $officer_id = $loan_officer ? $user->id : null;

        $query = DB::table('loan_applications');

        if ($loan_officer) {
            $query->where('assigned_officer_id', $officer_id);
        }

        $trends = $query->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") AS month'),
            DB::raw('COUNT(*) AS application_count'),
            DB::raw('SUM(amount) AS total_amount')
        )->where('created_at', '>=', Carbon::now()->subYear())
        ->groupBy('month')
        ->orderBy('month', 'ASC')
        ->get();

        $disbursement_query = DB::table('loan_accounts AS l_acc')
                            ->join('loan_applications AS l_app', 'l_acc.loan_application_id', '=', 'l_app.id');


        if ($loan_officer) {
            $disbursement_query->where('l_app.assigned_officer_id', $officer_id);
        }

        $disbursement_trend = $disbursement_query->select(
            DB::raw('DATE_FORMAT(l_acc.created_at, "%Y-%m") AS month'),
            DB::raw('COUNT(l_acc.id) AS disbursement_count'),
            DB::raw('SUM(l_acc.disbursed_amount) AS total_disbursed')
        )->where('l_acc.created_at', '>=', Carbon::now()->subYear())
        ->groupBy('month')
        ->orderBy('month', 'ASC')
        ->get();

        return response()->json([
            'success' => true,
            'user_role' => $loan_officer ? 'loan_officer' : 'admin',
            'data' => [
                'application_trend' => $trends,
                'disbursement_trend' => $disbursement_trend
            ]
        ]);

    }
}

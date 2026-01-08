<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoanAccountController extends Controller
{

    protected $sunctum = ['auth:sanctum'];
    public function __construct()
    {
        $this->middleware($this->sunctum);
    }

    public function performCreditCheck(Request $request, int $application_id): JsonResponse {

        $validator = Validator::make($request->all(), [
            'disbursed_amount' => 'required|numeric|min:1',
            'disbursement_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $application = LoanApplication::with('loanAccount')
                        ->where('status', 'approved')
                        ->findOrFail($application_id);

        if ($application->loanAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Loan already disbursed'
            ], 400);
        }

        $loan_account = LoanAccount::create([
            'loan_application_id' => $application_id,
            'account_number' => 'TEMP', // Will be updated after save
            'disbursed_amount' => $request->disbursed_amount,
            'outstanding_balance' => $request->disbursed_amount,
            'status' => 'active'
        ]);

        $loan_account->update([
            'account_number' => $loan_account->generateAccountNumber()
        ]);

        $application->update([
            'status' => 'disbursed',
            'disbursed_at' => $request->disbursement_date
        ]);

        $loan_account->createInstallments();

        AuditLog::create([
            'action' => 'loan_disbursed',
            'user_id' => $request->user()->id,
            'loan_application_id' => $application_id,
            'new_data' => $loan_account->toArray()
        ]);

        return response()->json([
            'success'=> true,
            'message' => 'Loan disbursed successfully',
            'data' => [
                'loan_account' => $loan_account,
                'installments' => $loan_account->installments
            ]
        ], 201);

    }

    public function index(Request $request): JsonResponse
    {
        $query = LoanAccount::with([
            'LoanApplication.user',
            'loanApplication.loanProduct'
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'like', "%{$search}%")
                    ->orWhereHas('loanApplication.user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('login', 'like', "%{$search}%");
                    });
            });
        }

        $accounts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $account = LoanAccount::with([
            'loanApplication.user',
            'loanApplication.loanProduct',
            'installments'
        ])->findOrFail($id);

        $stats = [
            'total_installments' => $account->installments->count(),
            'paid_installments' => $account->installments->where('status', 'paid')->count(),
            'pending_installments' => $account->installments->where('status', 'pending')->count(),
            'overdue_installments' => $account->installments->where('status', 'overdue')->count(),
            'total_paid' => $account->installments->where('status', 'paid')->sum('paid_amount'),
            'total_due' => $account->installments->where('status', 'pending')->sum('due_amount')
        ];

        return response()->json([
            'success' => true,
            'account' => $account,
            'stats' => $stats
        ]);
    }

    public function recordPayment(Request $request, int $account_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'installment_id' => 'required|exists:installments,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $installment = Installment::where('loan_account_id', $account_id)
                        ->findOrFail($request->installment_id);

        $installment->markAsPaid($request->amount);

        AuditLog::create([
            'action' => 'payment_received',
            'user_id' => $request->user()->id,
            'loan_application_id' => $installment->loanAccount->loan_application_id,
            'new_data' => [
                'installment_id' => $installment->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'transaction_id' => $request->transaction_id
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $installment
        ]);
    }

    public function upcomingInstallments(int $account_id): JsonResponse
    {
        $installments = Installment::with('loanAccount')
            ->where('loan_account_id', $account_id)
            ->where('status', 'pending')
            ->where('due_date', '>=', today())
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $account = LoanAccount::findOrFail($id);

        if ($account->outstanding_balance > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot close account with outstanding balance'
            ], 400);
        }

        $account->update([
            'status' => 'closed',
            'closed_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan account closed successfully'
        ]);
    }
}

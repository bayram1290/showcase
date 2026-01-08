<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Str;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LoanApplicationController extends Controller
{
    protected $sunctum = ['auth:sanctum'];

    public function __construct() {
        $this->middleware($this->sunctum);
    }

    public function getLoanProducts() {

        $products = LoanProduct::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    public function create (Request $request): JsonResponse {

        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|min:1',
            'purpose' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors()
            ], 422);
        }

        $product = LoanProduct::findOrFail($request->loan_product_id);

        if ($request->amount < $product->min_amount || $request->amount > $product->max_amount) {
            return response()->json([
                'success'=> false,
                'message'=> "Amount should be between {$product->min_amount} and {$product->max_amount}"
            ], 422);
        }

        if ($request->tenure < $product->min_tenure || $request->tenure > $product->max_tenure) {
            return response()->json([
                'success'=> false,
                'message'=> "Tenure should be between {$product->min_tenure} and {$product->max_tenure}"
            ], 422);
        }


        $application = LoanApplication::create([
            'user_id' => $request->user()->id,
            'loan_product_id' => $request->loan_product_id,
            'application_ref' => 'DRAFT-' . Str::random(10),
            'amount' => $request->amount,
            'tenure' => $request->tenure,
            'interest_rate' => $product->interest_rate,
            'purpose' => $request->purpose,
            'status' => 'draft',
            'application_data' => [
                'personal_info' => $request->user()->only([
                    'name',
                    'login',
                    'phone',
                    'date_of_birth',
                    'address',
                    'city',
                    'state',
                    'zip_code',
                    'country'
                ]),
                'employment_info' => $request->user()->only([
                    'monthly_income', 'employment_status',
                    'employer_name', 'employment_duration'
                ])
            ]
        ]);

        $monthly_installment = $application->calculateMonthlyInstallment();
        $total_payable = $application->calculateTotalPayable();

        $application->update([
            'monthly_installment' => round($monthly_installment, 2),
            'total_payable' => round($total_payable, 2)
        ]);

        AuditLog::create([
            'action' => 'application_created',
            'user_id' => $request->user()->id,
            'loan_application_id' => $application->id,
            'new_data' => $application->toArray()
        ]);

        return response()->json([
            'sucesss' => true,
            'message' => 'Loan application created as draft',
            'data' => $application,
            'calculation' => [
                'monthly_installment' => round($monthly_installment, 2),
                'total_payable' => round($total_payable, 2),
                'total_interest' => round($total_payable - $application->amount, 2)
            ]
        ]);

    }

    public function submit(Request $request, $id): JsonResponse {

        $application = LoanApplication::where('user_id', $request->user()->id)
                        ->where('id', $id)
                        ->where('status', 'draft')
                        ->firstOrFail();

        $application->update([
            'status' => 'submitted',
            'application_ref' => $application->generateApplicationRef(),
            'submitted_at' => now()
        ]);

        AuditLog::create([
            'action'=> 'application_submitted',
            'user_id'=> $request->user()->id,
            'loan_application_id'=> $application->id,
            'new_data'=> ['status' => 'submitted']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully',
            'data' => $application
        ]);

    }

    public function myApplications(Request $request): JsonResponse {

        $applications = LoanApplication::with(['loanProduct', 'assignedOfficer'])
                        ->where('user_id', $request->user()->id)
                        ->orderBy('created_at','desc')
                        ->paginate(10);

        return response()->json([
            'success' => true,
            'applications' => $applications
        ]);
    }


    public function show(Request $request, $id): JsonResponse {

        $application = LoanApplication::with([
            'loanProduct',
            'assignedOfficer',
            'documents',
            'loanAccount',
            'creditCheck'
        ])->where('user_id', $request->user()->id)
          ->findOrFail($id);

        return response()->json([
            'success' => true,
            'application' => $application
        ]);
    }

    public function index(Request $request): JsonResponse {

        $user = $request->user();

        if (!$user->isLoanOfficer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = LoanApplication::with([
            'user',
            'loanProduct',
            'assignedOfficer',
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('officer_id')) {
            $query->where('assigned_officer_id', $request->officer_id);
        }

        if ($request->has('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('application_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('login', 'like', "%{$search}%");
                    });
            });
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success'=> true,
            'data' => $applications
        ]);

    }

    public function updateStatus(Request $request, $id): JsonResponse {

        $user = $request->user();

        if (!$user->isLoanOfficer() || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:under_review,approved,rejected',
            'rejection_reason' => 'required_if:status,rejected',
            'assigned_officer_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors' => $validator->errors()
            ], 422);
        }

        $application = LoanApplication::findOrFail($id);
        $old_status = $application->status;

        $update_data = ['status' => $request->status];

        switch ($request->status) {
            case 'approved':
                $update_data['approved_at'] = now();
                $update_data['assigned_officer_id'] = $user->id;
                break;
            case 'rejected':
                $update_data['rejection_reason'] = $request->rejection_reason;
                break;
            case 'under_review':
                $update_data['assigned_officer_id'] = $request->assigned_officer_id ?? $user->id;
        }

        $application->update($update_data);

        AuditLog::create([
            'action' => 'application_status_updated',
            'user_id' => $user->id,
            'loan_application_id' => $application->id,
            'old_data' => ['status' => $old_status],
            'new_data' => $update_data
        ]);

        return response()->json([
            'success'=> true,
            'message'=> 'Loan application status updated successfully',
            'data' => $application
        ]);
    }

}

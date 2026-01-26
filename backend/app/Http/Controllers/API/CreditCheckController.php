<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditCheck;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CreditCheckController extends Controller
{
    protected $min_credit_score = 600;

    public function performCreditCheck(Request $request, $application_id): JsonResponse {

        $user = $request->user();

        if (!$user->isLoanOfficer() || $user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'credit_score'=> 'required|integer|min:300|max:850',
            'debt_to_income_ratio' => 'required|integer|min:0',
            'remarks' => 'nullable|string',
            'application_id' => 'required|exists:loan_applications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $application = LoanApplication::findOrFail($application_id);

        # Credit Bureau API here, with versons:
        #   1. Complete system integration API with all credentials or
        #   2. Complete API resource system (This can be another whole system, not a single API resources)
        # Actual API resource is hidden for public use due to the privacy policy of the company && government reasons.
        # This is simple/dummy example, no real API or integration.

        $credit_check = CreditCheck::updateOrCreate([
            ['loan_application_id' => $application_id],
            [
                'credit_score' => $request->credit_score,
                'debt_to_income_ratio' => $request->debt_to_income_ratio,
                'remarks' => $request->remarks,
                'checked_by' => $user->id,
                'credit_report_data' => [
                    'fake_data' => true,
                    'checked_at' => now()->toDateTimeString(),
                    'bureau' => 'Fake Credit Bureau'
                ]
            ]
        ]);

        if ($credit_check->credit_score < $this->min_credit_score) {
            $application->update([
                'status' => 'rejected',
                'rejection_reason' => 'Insufficient credit score',
            ]);

        } elseif ($application->status == 'under_review') {
            $application->update(['status' => 'approved']);
        }

        AuditLog::create([
            'action' => 'credit_check_performed',
            'user_id' => $user->id,
            'loan_application_id' => $application_id,
            'new_data' => $credit_check->toArray()
        ]);

        return response()->json([
            'success'=> true,
            'message' => 'Credit check performed successfully',
            'data' => $credit_check,
            'application_status' => $application->status
        ]);

    }

    public function getCreditCheck(int $id): JsonResponse
    {
        $application_id = LoanApplication::findOrFail($id)->id;

        $credit_check = CreditCheck::with('checkedBy')
                        ->where('loan_application_id', $application_id)->first();

        if (!$credit_check) {
            return response()->json([
                'success'=> false,
                'message'=> 'Credit check not found. Please perform a credit check first.',
            ], 404);
        }

        return response()->json([
            'success'=> true,
            'data' => $credit_check
        ]);

    }
}

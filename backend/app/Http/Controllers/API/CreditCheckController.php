<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditCheck;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CreditCheckController extends Controller
{
    protected $min_credit_score = 600;

    public function performCreditCheck(Request $request, $uuid): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'credit_score'=> 'required|integer|min:300|max:850',
            'debt_to_income_ratio' => 'required|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application = LoanApplication::where('application_uuid', $uuid)
                        ->where('status', 'under_review')
                        ->first();

        $credit_check = CreditCheck::updateOrCreate([
            ['loan_application_id' => $application->id],
            [
                'credit_score' => $request->credit_score,
                'debt_to_income_ratio' => $request->debt_to_income_ratio,
                'remarks' => $request->remarks,
                'checked_by' => $request->user()->id,
                'credit_report_data' => [
                    'fake_data' => true,
                    'checked_at' => Carbon::now()->toDateTimeString(),
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
            'user_id' => $request->user()->id,
            'loan_application_id' => $application->id,
            'new_data' => $credit_check->toArray()
        ]);

        return response()->json([
            'success'=> true,
            'message' => 'Credit check performed successfully',
            'data' => $credit_check,
            'application_status' => $application->status
        ], Response::HTTP_OK);

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

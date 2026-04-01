<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

use App\Models\User;
use App\Models\Borrower;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\LoanProduct;

use Carbon\Carbon;
use Str;
use Log;

class LoanApplicationController extends Controller
{

    public function create(Request $request): JsonResponse {

        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|min:1',
            'purpose' => 'required|string|max:500',
            'bank_branch' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = $request->user();

        if (!$borrower->is_verified) {
            return response()->json([
                'success'=> false,
                'message' => 'Your account needs to be verified before applying for a loan'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $product = LoanProduct::findOrFail($request->loan_product_id);

        if ($request->amount < $product->min_amount || $request->amount > $product->max_amount) {
            return response()->json([
                'success'=> false,
                'message'=> "Amount should be between {$product->min_amount} and {$product->max_amount}"
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->tenure < $product->min_tenure || $request->tenure > $product->max_tenure) {
            return response()->json([
                'success'=> false,
                'message'=> "Tenure should be between {$product->min_tenure} and {$product->max_tenure}"
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application = LoanApplication::create([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $request->loan_product_id,
            'application_ref' => 'DRAFT-' . Str::random(10),
            'amount' => $request->amount,
            'tenure' => $request->tenure,
            'interest_rate' => $product->interest_rate,
            'purpose' => $request->purpose,
            'status' => 'draft',
            'application_data' => [
                'personal_info' => $borrower->only([
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'address',
                    'date_of_birth',
                    'gender',
                    'citizenship',
                    'government_id_type',
                    'government_id_number',
                    'preferred_contact_method'
                ]),
                'employment_info' => $borrower->only([
                    'monthly_income',
                    'employment_status',
                    'employer_name',
                    'employment_duration',
                    'occupation',
                    'ssn',
                    'total_debt',
                    'monthly_expenses'
                ])
            ],
            'bank_branch' => $request->bank_branch
        ]);

        $monthly_installment = $application->calculateMonthlyInstallment();
        $total_payable = $application->calculateTotalPayable();
        $process_fee = ($product->processing_fee_percentage / 100) *100;

        $application->update([
            'monthly_installment' => round($monthly_installment, 2),
            'total_payable' => round($total_payable, 2),
            'processing_fee' => round($process_fee, 2),
            'total_fees' => round($process_fee, 2),
        ]);

        AuditLog::create([
            'action' => 'application_created',
            'borrower_id' => $borrower->id,
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
                'process_fee' => round($process_fee, 2),
                'total_interest' => round($total_payable - $application->amount, 2)
            ]
        ], Response::HTTP_CREATED);

    }


    public function submit(Request $request, $uuid): JsonResponse
    {

        $borrower = $request->user();
        $application = LoanApplication::where('borrower_id', $borrower->id)
                        ->where('application_uuid', $uuid)
                        ->where('status', 'draft')
                        ->first();

        if (!$application) {
            return response()->json([
                'success'=> false,
                'message' => 'Loan application not found or you do not have permission to submit this application.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$borrower->is_verified) {
            return response()->json([
                'success'=> false,
                'message' => 'Your account must be verified before submitting a loan application.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($borrower->is_blocked) {
            return response()->json([
                'success'=> false,
                'message' => 'Your account has been locked. Please contact support for more information.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$application->loanProduct || !$application->loanProduct->is_active) {
            return response()->json([
                'success'=> false,
                'message' => 'Loan product is not available yet.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validate_application = $this->validateApplication($application);
        if (!$validate_application['validness']) {
            return response()->json([
                'success'=> false,
                'message' => 'Application data is incomplete.',
                'errors' => $validate_application['errors']
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validate_document = $this->validateDocuments($application);
        if (!$validate_document['validness']) {
            return response()->json([
                'success'=> false,
                'message' => 'Required documents are missing.',
                'missing_documents' => $validate_document['missing_documents'],
                'required_documents' => $application->loanProduct->required_documents ?? []
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $active_applications = LoanApplication::where('borrower_id', $borrower->id)
                                ->whereIn('status', ['submitted', 'under_review'])
                                ->count();

        if ($active_applications > 2) {
            return response()->json([
                'success'=> false,
                'message' => 'You have too many active applications. Please, complete other applications first. Then, try again.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($borrower->monthly_income) && isset($application->monthly_installment) && $borrower->monthly_income > 0) {
            $debt_ratio = ($application->monthly_installment / $borrower->monthly_income) * 100;

            if ($debt_ratio > 50) {
                return response()->json([
                    'success'=> false,
                    'message' => 'Your monthly installment is too high. Please, reduce your monthly installment. Then, try again.',
                    'debt_ratio' => $debt_ratio,
                    'monthly_installment' => $application->monthly_installment,
                    'monthly_income' => $borrower->monthly_income
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $application_ref = $application->generateApplicationRef();
        $update_data = [
            'status' => 'submitted',
            'application_ref' => $application_ref,
            'submitted_at' => Carbon::now(),
        ];

        if ($this->shouldAssignOfficer($application)) {
            $assigned_officer_id = $this->getAvailableOfficer();
            if ($assigned_officer_id) {
                $update_data['assigned_officer_id'] = $assigned_officer_id;
                $update_data['status'] = 'under_review';
                $application->update($update_data);
            }
        }

        $old_data = $application->toArray();
        $application->update($update_data);

        AuditLog::create([
            'action'=> 'application_submitted',
            'user_id'=> $borrower->id,
            'loan_application_id'=> $application->id,
            'old_data'=> $old_data,
            'new_data'=> $update_data,
        ]);

        if ( !is_null($borrower->email)) {
            $this->sendSubmissionNotification($application, $borrower);
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully',
            'data' => $application->fresh()->load(['loanProduct']),
            'submission_details' => [
                'application_ref' => $application_ref,
                'submitted_at' => Carbon::parse($application->submitted_at)->format('d-m-Y'),
                'assigned_officer_id' => $application->assigned_officer_id,
                'estimated_review_time' => '3-7 business days',
                'next_steps' => [
                    'Application will be reviewed by our team.',
                    'You may be contacted for additional information.',
                    'Check your email for updates or call us at +993 (12) 456-7890.'
                ],
            ]
        ], Response::HTTP_OK);
    }


    public function myApplications(Request $request): JsonResponse
    {

        $borrower = $request->user();
        $applications = LoanApplication::with(['loanProduct', 'assignedOfficer'])
                        ->where('borrower_id', $borrower->id)
                        ->orderBy('created_at','desc')
                        ->paginate(10);

        return response()->json([
            'success' => true,
            'applications' => $applications
        ], Response::HTTP_OK);
    }


    /**
     * Get a loan application by uuid
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(Request $request, $uuid): JsonResponse
    {
        $application = LoanApplication::with([
            'loanProduct',
            'assignedOfficer',
            'documents',
            'loanAccount',
            'creditCheck'
        ])->where('application_uuid', $uuid);

        if ($application->first() !== NULL && $request->user()->role == 'officer') {
            $application->where('assigned_officer_id', $request->user()->id);
        }

        return response()->json([
            'success' => true,
            'application' => $application->first() ?? []
        ], Response::HTTP_OK);
    }


    /**
     * Index API for Loan Applications
     *
     * @api {get} /api/loan-applications
     * @apiName IndexLoanApplications
     * @apiGroup Loan Applications
     * @apiParam {string} status Filter by loan application status
     * @apiParam {integer} borrower_id Filter by borrower ID
     * @apiParam {integer} officer_id Filter by assigned officer ID
     * @apiParam {date} date_from Filter by date from
     * @apiParam {date} date_to Filter by date to
     * @apiParam {string} search Filter by application ref, borrower first name, last name, or phone
     */
    public function index(Request $request): JsonResponse
    {

        $query = LoanApplication::with([
            'borrower',
            'loanProduct',
            'assignedOfficer'
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('borrower_id')) {
            $query->where('borrower_id', $request->borrower_id);
        }

        if ($request->has('officer_id')) {
            $query->where('assigned_officer_id', $request->officer_id);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('application_ref', 'like', "%{$search}%")
                    ->orWhereHas('borrower', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success'=> true,
            'data' => $applications
        ], Response::HTTP_OK);

    }


    public function updateStatus(Request $request, $uuid): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:under_review,approved,rejected',
            'rejection_reason' => 'required_if:status,rejected',
            'review_notes' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = auth()->user();

        if ($user->role !== 'officer') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->isLoanOfficer()) {
            $application = LoanApplication::where('application_uuid', $uuid)
                        ->whereIn('status', ['submitted', 'under_review', 'rejected', 'approved'])
                        ->first();
        } else {
            $application = LoanApplication::where('application_uuid', $uuid)
                        ->where('status', 'submitted')
                        ->whereNotNull('assigned_officer_id', $user->id)
                        ->first();
        }

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Loan application not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $old_application = serialize($application->toArray());
        $update_data = [
            'status' => $request->status
        ];

        switch ($request->status) {
            case 'under_review':
                $update_data['review_score'] = $this->calculateInitialReviewScore($application);
                $update_data['review_notes'] = $request->review_notes;
                $update_data['reviewed_at'] = Carbon::now();
                break;

            case 'rejected':
                $update_data['rejection_reason'] = $request->rejection_reason;
                $update_data['rejected_at'] = Carbon::now();
            break;
            case 'approved':
                $update_data['approved_at'] = Carbon::now();
            break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => "Invalid loan application update status: {$request->status}"
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application->update($update_data);

        AuditLog::create([
            'action' => 'application_status_changed_to_' . $request->status,
            'user_id' => $user->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_application,
            'new_data' => serialize($application->fresh()->toArray())
        ]);

        return response()->json([
            'success'=> true,
            'message'=> 'Loan application status updated successfully',
        ], Response::HTTP_OK);
    }


    public function assignOfficer($uuid): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isLoanOfficer()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], Response::HTTP_FORBIDDEN);
        }

        $application = LoanApplication::where('application_uuid', $uuid)
                        ->whereNull('assigned_officer_id')
                        ->where('status', 'submitted')
                        ->first();

        if (!$application) {
            return response()->json([
                'success'=> false,
                'message'=> 'No submitted loan application found.'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($application->assigned_officer_id) {
            return response()->json([
                'success'=> false,
                'message'=> 'Loan application already assigned.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($application->status !== 'submitted') {
            return response()->json([
                'success'=> false,
                'message' => 'Status of the application must be "submitted".'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $assigned_officer_id = $this->getAvailableOfficer();

        if (!$assigned_officer_id) {
            return response()->json([
                'success'=> false,
                'message'=> 'No available loan officer found.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application->update([
            'assigned_officer_id' => $assigned_officer_id,
        ]);

        return response()->json([
            'success'=> true,
            'message'=> 'Officer is assigned to the loan successfully.'
        ], Response::HTTP_OK);

    }


    public function cancel(Request $request, $uuid): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'sometimes|string|max:200'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $borrower = $request->user();

        $application = LoanApplication::where('borrower_id', $borrower->id)
                        ->where('application_uuid', $uuid)
                        ->with(['loanAccount'])
                        ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Loan application not found or you do not have permission to cancel this application.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $cancel_statuses = ['draft', 'submitted', 'under_review'];

        if (!in_array($application->status, $cancel_statuses)) {

            $status_map = [
                'approved' => 'approved application',
                'disbursed' => 'disbursed loans',
                'rejected' => 'rejected applications',
                'closed' => 'closed application',
                'cancelled' => 'already cancelled application'
            ];

            $message = isset($status_map[$application->status]) ? "Cannot cancel {$status_map[$application->status]}" : "Cannot cancel application with status: {$application->status}. Only applications with status: " . implode(', ', $cancel_statuses) . ' can be cancelled';

            return response()->json([
                'success' => false,
                'message' => $message
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($application->loanAccount()->exists()) {

            $loan_status = $application->loanAccount->status;

            if (in_array($loan_status, ['active', 'disbursed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel application that has already been disbursed/activated loan account. Please, contact support for more information.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $old_data = ['status' => $application->status, 'rejection_reason' => $application->rejection_reason];
        $update_data = [
            'status' => 'cancelled',
            'closed_at' => Carbon::now(),
            'rejection_reason' => $request->cancellation_reason ?? 'Application cancelled by borrower',
        ];

        $application->update($update_data);

        AuditLog::create([
            'action' => $update_data['rejection_reason'],
            'borrower_id' => $borrower->od,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $update_data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan application cancelled successfully',
            'data' => [
                'id' => $application->id,
                'application_ref' => $application->application_ref,
                'previous_status' => $old_data['status'],
                'new_status' => 'cancelled',
                'cancellation_reason' => $update_data['rejection_reason'],
                'cancelled_at' => $update_data['closed_at']
            ]
        ], Response::HTTP_OK);
    }



    public function restoreToDraft(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'update_data' => 'nullable|array',
            'update_data.amount' => 'nullable|numeric|min:1',
            'update_data.tenure' => 'nullable|integer|min:1',
            'update_data.purpose' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $borrower = $request->user();
        
        // Find application
        $application = LoanApplication::with(['loanProduct', 'loanAccount'])
                        ->where('borrower_id', $borrower->id)
                        ->where('id', $id)
                        ->where('status', 'cancelled')
                        ->first();
        
        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Cancelled application not found'
            ],  Response::HTTP_NOT_FOUND);
        }
        
        // Validate restoration
        $validationResult = $this->validateRestoration($application);
        
        if (!$validationResult['can_restore']) {
            return response()->json([
                'success' => false,
                'message' => $validationResult['message']
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Prepare update data
        $updateData = [
            'status' => 'draft',
            'application_ref' => 'DRAFT-' . Str::random(10),
            'rejection_reason' => null,
            'review_notes' => null,
            'review_score' => null,
            'assigned_officer_id' => null,
            'closed_at' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'disbursed_at' => null,
        ];
        
        // Allow partial updates if provided
        if ($request->has('update_data')) {
            $allowedUpdates = ['amount', 'tenure', 'purpose', 'bank_branch'];
            foreach ($request->update_data as $key => $value) {
                if (in_array($key, $allowedUpdates)) {
                    $updateData[$key] = $value;
                }
            }
            
            // Recalculate if amount or tenure changed
            if (isset($updateData['amount']) || isset($updateData['tenure'])) {
                $amount = $updateData['amount'] ?? $application->amount;
                $tenure = $updateData['tenure'] ?? $application->tenure;
                
                // Validate against loan product
                $product = $application->loanProduct;
                
                if ($amount < $product->min_amount || $amount > $product->max_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => "Amount must be between {$product->min_amount} and {$product->max_amount}"
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                
                if ($tenure < $product->min_tenure || $tenure > $product->max_tenure) {
                    return response()->json([
                        'success' => false,
                        'message' => "Tenure must be between {$product->min_tenure} and {$product->max_tenure}"
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                
                // Recalculate installments
                $application->amount = $amount;
                $application->tenure = $tenure;
                $monthlyInstallment = $application->calculateMonthlyInstallment();
                $totalPayable = $application->calculateTotalPayable();
                
                $updateData['monthly_installment'] = round($monthlyInstallment, 2);
                $updateData['total_payable'] = round($totalPayable, 2);
            }
        }
        
        // Update application
        $oldData = $application->toArray();
        $application->update($updateData);
        
        // Create audit log
        AuditLog::create([
            'action' => 'application_restored',
            'borrower_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'old_data' => $oldData,
            'new_data' => $application->fresh()->toArray(),
            'notes' => 'Restored to draft from cancelled status'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Application restored to draft successfully',
            'data' => $application->fresh(),
            'allow_updates' => true,
            'updated_fields' => array_keys($request->update_data ?? [])
        ], Response::HTTP_OK);

    }


    private function validateRestoration(LoanApplication $application): array
    {
        // Time limit (30 days max)
        $cancelledTime = $application->closed_at ?? $application->updated_at;
        if ($cancelledTime && now()->diffInDays($cancelledTime) > 30) {
            return ['can_restore' => false, 'message' => 'Cannot restore after 30 days'];
        }
        
        // Check loan product
        if (!$application->loanProduct) {
            return ['can_restore' => false, 'message' => 'Loan product not found'];
        }
        
        if (!$application->loanProduct->is_active) {
            return ['can_restore' => false, 'message' => 'Loan product is no longer available'];
        }
        
        // Check if loan was disbursed
        if ($application->loanAccount) {
            return ['can_restore' => false, 'message' => 'Cannot restore disbursed loan'];
        }
        
        // Check borrower eligibility (optional)
        $borrower = $application->borrower;
        if (!$borrower->is_verified) {
            return ['can_restore' => false, 'message' => 'Borrower account is not verified'];
        }
        
        return ['can_restore' => true, 'message' => ''];
    }


    public function adminRestore(Request $request, $id): JsonResponse
    {
        $admin = $request->user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'target_status' => 'required|in:draft,submitted,under_review',
            'assigned_officer_id' => 'nullable|exists:users,id',
            'restoration_reason' => 'nullable|string|max:500',
            'update_amount' => 'nullable|numeric|min:1',
            'update_tenure' => 'nullable|integer|min:1',
            'update_purpose' => 'nullable|string|max:500',
            'skip_validation' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $application = LoanApplication::with([
            'borrower',
            'loanProduct',
            'loanAccount',
            'assignedOfficer'
        ])->find($id);
        
        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found'
            ], 404);
        }
        
        // Admin can restore from various statuses, not just cancelled
        $restorableStatuses = ['cancelled', 'rejected', 'withdrawn'];
        if (!in_array($application->status, $restorableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot restore application with status: {$application->status}"
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Check if already has active loan account
        if ($application->loanAccount && $application->loanAccount->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot restore application with active loan'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        $targetStatus = $request->target_status;
        $oldStatus = $application->status;
        
        // Validate loan product if not skipping
        if (!$request->skip_validation && $application->loanProduct) {
            $product = $application->loanProduct;
            
            // Check product availability
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan product is inactive',
                    'product' => $product->name
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Validate amount if updating
            if ($request->has('update_amount')) {
                $amount = $request->update_amount;
                if ($amount < $product->min_amount || $amount > $product->max_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => "Amount must be between {$product->min_amount} and {$product->max_amount}"
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
            
            // Validate tenure if updating
            if ($request->has('update_tenure')) {
                $tenure = $request->update_tenure;
                if ($tenure < $product->min_tenure || $tenure > $product->max_tenure) {
                    return response()->json([
                        'success' => false,
                        'message' => "Tenure must be between {$product->min_tenure} and {$product->max_tenure}"
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }
        
        // Prepare update
        $updateData = [
            'status' => $targetStatus,
            'rejection_reason' => null,
            'closed_at' => null,
            'restored_by_admin_id' => $admin->id,
            'restored_at' => now(),
            'restoration_reason' => $request->restoration_reason,
        ];
        
        // Handle field updates
        if ($request->has('update_amount')) {
            $updateData['amount'] = $request->update_amount;
        }
        
        if ($request->has('update_tenure')) {
            $updateData['tenure'] = $request->update_tenure;
        }
        
        if ($request->has('update_purpose')) {
            $updateData['purpose'] = $request->update_purpose;
        }
        
        // Recalculate if amount or tenure changed
        if (isset($updateData['amount']) || isset($updateData['tenure'])) {
            $application->fill($updateData);
            $updateData['monthly_installment'] = round($application->calculateMonthlyInstallment(), 2);
            $updateData['total_payable'] = round($application->calculateTotalPayable(), 2);
        }
        
        // Handle status-specific updates
        switch ($targetStatus) {
            case 'draft':
                $updateData['application_ref'] = 'DRAFT-' . Str::random(10);
                $updateData['review_notes'] = null;
                $updateData['review_score'] = null;
                $updateData['assigned_officer_id'] = null;
                $updateData['submitted_at'] = null;
                $updateData['approved_at'] = null;
                $updateData['disbursed_at'] = null;
                break;
                
            case 'submitted':
                $updateData['submitted_at'] = now();
                $updateData['application_ref'] = $application->generateApplicationRef();
                break;
                
            case 'under_review':
                $updateData['submitted_at'] = now();
                $updateData['application_ref'] = $application->generateApplicationRef();
                $updateData['assigned_officer_id'] = $request->assigned_officer_id 
                    ?? $application->assigned_officer_id 
                    ?? null;
                break;
        }
        
        // Save changes
        $oldData = $application->toArray();
        $application->update($updateData);
        
        // Audit log
        AuditLog::create([
            'action' => 'admin_restored_application',
            'user_id' => $admin->id,
            'borrower_id' => $application->borrower_id,
            'loan_application_id' => $application->id,
            'old_data' => ['status' => $oldStatus] + $oldData,
            'new_data' => $application->fresh()->toArray(),
            'notes' => "Restored from {$oldStatus} to {$targetStatus}" . 
                    ($request->restoration_reason ? ": {$request->restoration_reason}" : "")
        ]);
        
        return response()->json([
            'success' => true,
            'message' => "Application #{$application->application_ref} restored from {$oldStatus} to {$targetStatus}",
            'data' => [
                'application' => $application->fresh()->load(['borrower', 'loanProduct', 'assignedOfficer']),
                'admin_action' => [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->full_name ?? $admin->email,
                    'action' => 'restore',
                    'from_status' => $oldStatus,
                    'to_status' => $targetStatus,
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]
        ], Response::HTTP_OK);
    }

    private function validateApplication(LoanApplication $application): array
    {
        $errs = [];
        $borrower = Borrower::find($application->borrower_id)->first();

        $personal_info = $borrower->only([
            'first_name',
            'last_name',
            'phone',
            'address',
            'date_of_birth',
            'gender',
            'citizenship',
            'government_id_type',
            'government_id_number',
        ]);

        $employment_info = $borrower->only([
            'employment_status',
            'employer_name',
            'employment_duration',
            'occupation',
        ]);

        $financial_info = $borrower->only([
            'monthly_income',
            'total_debt',
            'monthly_expenses',
            'ssn',
        ]);

        $application_data = [
            'personal_info' => $personal_info,
            'employment_info' => $employment_info,
            'financial_info' => $financial_info,
        ];

        $personal_empty = true;
        foreach ($application_data['employment_info'] as $key => $value) {
            if (!is_null($value)) {
                $personal_empty = false;
                break;
            }
        }
        if ( $personal_empty ) {
            $errs['personal_info'] = 'Personal information is required';
        } else {
            $personal_info = $application_data['personal_info'];
            $required_personal = ['first_name', 'last_name', 'phone', 'address', 'date_of_birth', 'gender', 'citizenship', 'government_id_type', 'government_id_number'];

            foreach ($required_personal as $field) {
                if (empty($personal_info[$field] ?? null)) {
                    $errs['personal_info.' . $field] = 'Personal info: "' . str_replace('_', ' ', $field) . '" is required';
                }
            }
        }

        $employment_empty = true;
        foreach ($application_data['employment_info'] as $key => $value) {
            if (!is_null($value)) {
                $employment_empty = false;
                break;
            }
        }
        if ($employment_empty) {
            $errs['employment_info'] = 'Employment information is required';
        } else {
            $employment_info = $application_data['employment_info'];
            $required_employment = ['employment_status', 'employer_name', 'employment_duration', 'occupation'];

            foreach ($required_employment as $field) {
                if (empty($employment_info[$field] ?? null)) {
                    $errs['employment_info.' . $field] = 'Employment info: "' . str_replace('_', ' ', $field) . '" is required';
                }
            }
        }

        $financial_empty = true;
        foreach ($application_data['financial_info'] as $key => $value) {
            if (!is_null($value)) {
                $financial_empty = false;
                break;
            }
        }
        if ($financial_empty) {
            $errs['financial_info'] = 'Financial information is required';
        } else {
            $financial_info = $application_data['financial_info'];
            $required_financial = ['monthly_income', 'total_debt', 'monthly_expenses', 'ssn'];
            foreach ($required_financial as $field) {
                if (empty($financial_info[$field] ?? null)) {
                    $errs['financial_info.' . $field] = 'Financial info: "' . str_replace('_', ' ', $field) . '" is required';
                }
            }
        }

        return [
            'validness' => empty($errs),
            'errors' => $errs
        ];
    }


    private function validateDocuments(LoanApplication $application): array
    {
        $required_documents = $application->loanProduct->required_documents ?? [];

        if (empty($required_documents)) {
            return [
                'validness' => true,
                'missing_documents' => []
            ];
        }

        $uploaded_documents = $application->documents->pluck('document_type')->toArray();
        $missing_documents = array_diff($required_documents, $uploaded_documents);

        return [
            'validness' => empty($missing_documents),
            'missing_documents' => array_values($missing_documents)
        ];
    }

    private function calculateInitialReviewScore(LoanApplication $application): int
    {
        $score = 50;

        $application_data = $application->application_data ?? [];
        $employment_info = $application_data['employment_info'] ?? [];

        if (($employment_info['employment_duration'] ?? 0) > 12) {
            $score += 10;
        } elseif (($employment_info['employment_duration'] ?? 0) > 6) {
            $score += 5;
        }

        $monthly_income = $employment_info['monthly_income'] ?? 0;
        $total_debt = $employment_info['total_debt'] ?? 0;

        if ($monthly_income > 0) {
            $debt_ratio = ($total_debt / $monthly_income) * 100;

            if ($debt_ratio < 20) {
                $score += 15;
            } elseif ($debt_ratio < 40) {
                $score += 5;
            } else {
                $score -= 10;
            }
        }

        if ($monthly_income > 0) {
            $amount_ratio = ($application->amount / $monthly_income) * 100;

            if ($amount_ratio < 100) {
                $score += 10;
            } else if ($amount_ratio > 300) {
                $score -= 10;
            }
        }

        return  max(0, min(100, $score));
    }

    private function shouldAssignOfficer(LoanApplication $application): bool
    {
        return $application->assigned_officer_id == null && $application->status == 'submitted' && in_array($application->loan_type, ['personal', 'education']);
    }

    /**
     * The function to assign an available loan officer to a loan application.
     *
     * It will return the id of the officer with the least number of pending applications or null.
     *
     * @return int|null
     */
    private function getAvailableOfficer(): ?int
    {
        $available_officers = \DB::table('users AS u')
                            ->where('role', 'officer')
                            ->where('is_active', true)
                            ->leftJoin('loan_applications AS l_app', function ($join) {
                                $join->on('u.id', '=', 'l_app.assigned_officer_id')
                                    ->where('l_app.status', ['submitted', 'under_review']);
                            })
                            ->select('u.id', \DB::raw('COUNT(l_app.id) AS pending_applications'))
                            ->groupBy('u.id')
                            ->orderBy('pending_applications', 'ASC')
                            ->first();

        return $available_officers->id ?? null;
    }

    private function sendSubmissionNotification(LoanApplication $application, Borrower $borrower): void
    {
        $application_details = [
            'Application reference' => $application->application_ref,
            'Borrower full name' => $borrower->gender == 'M' ? 'Mr. ' : 'Ms./Mrs. ' . ($borrower->first_name . ' ' . $borrower->last_name),
            'Loan amount' => number_format($application->amount, 2),
            'Loan type' => $application->loan_type,
            'loan term' => $application->loan_term . ' in months',
            'purpose' => $application->purpose,
            'Submitted date' => Carbon::now()->format('d-m-Y H:i:s'),
        ];

        $this->sendGenericMail(
            $borrower->email,
            'Submit for loan application successful',
            [
                'message' => 'Your loan application has been submitted successfully. Please wait for the loan officer to review your application and get back to you.',
                'application_details' => $application_details,
                'next_steps' => 'Our team will review your application within 2-3 business days. You will be notified of any updates via email or phone call. Please do not reply to this email.'
            ],
            [
                'application_id' => $application->id,
                'borrower_name' => $borrower->gender == 'M' ? 'Mr. ' : 'Ms./Mrs. ' . ($borrower->first_name . ' ' . $borrower->last_name)
            ]
        );

        if ($application->assigned_officer_id) {
            $officer = User::find($application->assigned_officer_id);

            if ($officer) {
                AuditLog::create([
                    'user_id' => $officer->id,
                    'loan_application_id' => $application->id,
                    'action' => 'application_submitted',
                    'description' => 'Loan application submitted by ' . ($borrower->gender == 'M' ? 'Mr. ' : 'Ms./Mrs. ' . ($borrower->first_name . ' ' . $borrower->last_name))
                ]);

                $this->sendGenericMail(
                    $officer->email,
                    'New loan application submitted',
                    [
                        'message' => 'A new loan application has been assigned to you for review.',
                        'application_details' => $application_details,
                        'action_required' => 'Please review this application at your earliest convenience. Please do not reply to this email.',
                    ],
                    [
                        'application_id' => $application->id,
                        'borrower_id' => $borrower->id,
                        'priority' => 'Normal'
                    ]
                );
            }
        }

        if (config('helper.notify_admin_on_new_loan_submission')) {

            $this->sendGenericMail(
                User::getAdminPublicField('email'),
                'New loan application submitted',
                [
                    'message' => 'A new loan application has been submitted to the system.',
                    'application_details' => $application_details,
                    'system_notification' => 'This is a system notification for monitoring reasons. Please do not reply to this email.',
                ],
                [
                    'application_id' => $application->id,
                    'borrower_id' => $borrower->id,
                    'notification_type' => 'New submission'
                ]
            );
        }
    }

    /**
     * Helper method to send generic email using the universal Mailable
     *
     * @param string $reciever
     * @param string $subject
     * @param string|array $content
     * @param array $data
     * @param string|null $view
     * @return void
     */
    private function sendGenericMail(string $reciever, string $subject, $content, array $data = [], ?string $view = null): void
    {
        try {
            \Mail::to($reciever)->send(new \App\Mail\NewApplicationSubmittedMail($subject, $content, $data, $view));

            Log::info('Email sent successfully', [
                'to' => $reciever,
                'subject' => $subject,
                'type' => 'generic_email'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'to' => $reciever,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
        }
    }

}

<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LoanApplicationService
{
    protected OfficerAssignmentService $officerService;
    protected NotificationService $notificationService;
    const INITIAL_REVIEW_SCORE = 50;
    const DEBT_THRESHOLDS = [20, 40];
    const AMOUNT_THRESHOLDS = [100, 300];
    const REQUIRED_PERSONAL_DETAILS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'citizenship',
        'government_id_type',
        'government_id_number'
    ];
    const REQUIRED_EMPLOYMENT_DETAILS = [
        'employment_status',
        'employer_name',
        'employment_duration',
        'occupation'
    ];

    const REQUIRED_FINANCIAL_DETAILS = [
        'monthly_income',
        'total_debt',
        'monthly_expenses',
        'ssn'
    ];

    /**
     * Constructs a new instance of the LoanApplicationService.
     *
     * @param OfficerAssignmentService $officerService The service responsible for assigning officers to loan applications.
     * @param NotificationService $notificationService The service responsible for sending notifications related to loan applications.
     */
    public function __construct(
        OfficerAssignmentService $officerService,
        NotificationService $notificationService,
    ) {
        $this->officerService = $officerService;
        $this->notificationService = $notificationService;
    }

    /**
     * Creates a new draft loan application for the given borrower and data.
     *
     * @param Borrower $borrower The borrower for whom the loan application is being created.
     * @param array $data The data for the loan application.
     *
     * @throws \Exception If the amount or tenure is not within the product limits.
     *
     * @return LoanApplication The newly created loan application.
     */
    public function createDraft(Borrower $borrower, array $data): LoanApplication
    {
        $product = LoanProduct::find($data['loan_product_id']);

        if (!$product->isValidForLoan($data['amount'], $data['tenure'])) {
            throw new \Exception("Amount or tenure not within product limits.");
        }

        $application = LoanApplication::create([
            'loan_type' => $data['type'],
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_ref' => 'DRAFT-' . Str::random(10),
            'amount' => $data['amount'],
            'tenure' => $data['tenure'],
            'interest_rate' => $product->interest_rate,
            'purpose' => $data['purpose'],
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
                    'employment_status',
                    'employer_name',
                    'employment_duration',
                    'occupation'
                ]),
                'financial_info' => $borrower->only([
                    'monthly_income',
                    'total_debt',
                    'monthly_expenses',
                    'ssn'
                ])
            ],
            'bank_branch' => $data['bank_branch'],
        ]);

        $monthly_installment = $application->calculateMonthlyInstallment();
        $total_payable = $application->calculateTotalPayable();
        $processing_fee = ($product->processing_fee_percentage / 100) * $application->amount;

        $application->update([
            'monthly_installment' => round($monthly_installment, 2),
            'total_payable' => round($total_payable, 2),
            'processing_fee' => round($processing_fee, 2),
            'total_fees' => round($total_payable + $processing_fee, 2)
        ]);

        AuditLog::create([
            'action' => 'application_created',
            'borrower_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'new_data' => $application->toArray(),
        ]);

        return $application->fresh();
    }

    /**
     * Submits a loan application.
     *
     * @throws \Exception If the application is not a draft, borrower is not verified, borrower is blocked, loan product is not available, application has missing required data or documents, or monthly installment exceeds 50% of monthly income.
     *
     * @param LoanApplication $application The loan application to be submitted.
     * @param Borrower $borrower The borrower of the loan application.
     *
     * @return LoanApplication The submitted loan application.
     */
    public function submit(LoanApplication $application, Borrower $borrower): LoanApplication
    {
        if ($application->status !== 'draft') {
            throw new \Exception("Only draft applications can be submitted.");
        }

        if (!$borrower->email_verified_at) {
            throw new \Exception("Account is not verified yet.");
        }

        if ($borrower->is_blocked) {
            throw new \Exception("Account is blocked.");
        }

        if (!$application->loanProduct || !$application->loanProduct->is_active) {
            throw new \Exception("Loan product is not available.");
        }

        // Validate application required data and application documents
        $this->validateApplicationData($application);
        $this->validateApplicationDocuments($application);

        // Check active application limit
        $active_application_count = LoanApplication::where('borrower_id', $borrower->id)
                                    ->whereIn('status', ['submitted', 'under_review'])
                                    ->count();
        if ($active_application_count > config('helper.active_application_limit')) {
            throw new \Exception("You have reached the maximum number of active applications.");
        }

        // Check if monthly installment exceeds 50% of monthly income
        if ($borrower->monthly_income > 0) {
            $debt_ratio = ($application->monthly_installment / $borrower->monthly_income) * 100;
            if ($debt_ratio > config('helper.max_debt_ratio')) {
                throw new \Exception("Monthly installment exceeds 50% of monthly income (Debt ratio: {$debt_ratio}%).");
            }
        }

        $ref = $application->generateApplicationRef();

        $update_data = [
            'status' => 'submitted',
            'application_ref' => $ref,
            'submitted_at' => Carbon::now(),
        ];

        if ($this->officerService->shouldAssignOfficer($application)) {
            $officer_id = $this->officerService->getAvailableOfficers();
            if ($officer_id) {
                $update_data['officer_id'] = $officer_id;
                $update_data['status'] = 'under_review';
            }
        }

        $old_data = $application->toArray();
        $application->update($update_data);

        AuditLog::create([
            'action' => 'application_submitted',
            'borrower_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $application->toArray(),
        ]);

        $this->notificationService->applicationSubmitted($application, $borrower);
        return $application->fresh();
    }

    /**
     * Updates the status of a loan application.
     *
     * @param LoanApplication $application The loan application to be updated.
     * @param array $data The data to be updated.
     * @param User $user The user performing the update.
     *
     * @throws \Exception If the user does not have permission to update the application.
     * @throws \Exception If the status transition is invalid.
     */
    public function underReviewStatus(LoanApplication $application, array $data, User $user): void
    {
        if (!in_array($user->role, ['admin', 'loan_officer', 'officer'])) {
            throw new \Exception("You don't have permission to update this application.");
        }

        if ($user->role === 'officer' && $application->assigned_officer_id !== $user->id) {
            throw new \Exception("You don't have permission to update this application.");
        }

        if (in_array($user->role, ['admin', 'loan_officer']) && is_null($application->assigned_officer_id) ) {
            throw new \Exception("Please, assign an officer to this application first.");
        }

        $old_status =$application->status;
        $update_data = [
            'status' => 'under_review',
            'review_score' => $this->calculateIniitialReviewScore($application),
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => Carbon::now(),
        ];

        $old_data = $application->toArray();
        $application->update($update_data);

        AuditLog::create([
            'action' => 'application_status_changed_from_' . $old_status . '_to_' . $update_data['status'],
            'user_id' => $user->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $application->toArray(),
        ]);
    }


    /**
     * Cancels a loan application.
     *
     * Throws an exception if:
     *  - the current user does not own the application,
     *  - the application is not in the draft, submitted, or under_review status,
     *  - the application has an active or disbursed loan account.
     *
     * @param LoanApplication $application The loan application to be cancelled.
     * @param Borrower $borrower The current user who is cancelling the application.
     * @param string|null $reason The reason for cancelling the application.
     */
    public function cancel(LoanApplication $application, Borrower $borrower, ?string $reason = null): void
    {
        if ($application->borrower_id !== $borrower->id) {
            throw new \Exception('You do not own this application');
        }

        $cancelable_statuses = ['draft', 'submitted', 'under_review'];
        if (!in_array($application->status, $cancelable_statuses)) {
            throw new \Exception("You cannot cancel application with status: {$application->status}.");
        }

        if ($application->loanAccount && in_array($application->loanAccount->status, ['active', 'disbursed'])) {
            throw new \Exception('You cannot cancel a disbursed loan.');
        }

        $old_data = $application->toArray();
        $application->update([
            'status' => 'cancelled',
            'closed_at' => Carbon::now(),
            'rejection_reason' => $reason ?? 'Cancelled by borrower'
        ]);

        AuditLog::create([
            'action' => 'application_cancelled',
            'borrower_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $application->toArray(),
        ]);
    }

    /**
     * Restore a cancelled loan application to draft status.
     *
     * Throws an exception if:
     *  - the application is not in the cancelled status,
     *  - the application is older than 30 days,
     *  - the loan product is no longer available,
     *  - the updated amount/tenure are outside product limits.
     *
     * @param int $applicationId The ID of the loan application to be restored.
     * @param Borrower $borrower The current user who is restoring the application.
     * @param array $data The data to be updated.
     *
     * @return LoanApplication The restored loan application.
     */
    public function restoreByBorrower(int $applicationId, Borrower $borrower, array $data = []): LoanApplication
    {
        $application = LoanApplication::find($applicationId)
                        ->where('status', 'cancelled')
                        ->where('borrower_id', $borrower->id)
                        ->first();

        if (!$application) {
            throw new \Exception('Canncelled application not found');
        }

        $cancelled_at = $application->closed_at ?? $application->updated_at;
        if ($cancelled_at && Carbon::now()->diffInDays(Carbon::parse($cancelled_at)) > 30) {
            throw new \Exception('You cannot restore applications older thatn 30 days.');
        }

        if (!$application->loanProduct || !$application->loanProduct->is_active) {
            throw new \Exception('The loan product is no longer available.');
        }

        $restore_data = [
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

        $update_values = ['amount', 'tenure', 'purpose', 'bank_branch'];
        foreach ($data as $key => $value) {
            if (in_array($key, $update_values)) {
                $restore_data[$key] = $value;
            }
        }

        if (isset($restore_data['amount']) || isset($restore_data['tenure'])) {
            $amount = $restore_data['amount'] ?? $application->amount;
            $tenure = $restore_data['tenure'] ?? $application->tenure;
            $product = $application->laonProduct;
            if (!$product->isValidForLoan($amount, $tenure)) {
                throw new \Exception('Updated amount/tenure are outside product limits');
            }
            $application->amount = $amount;
            $application->tenure = $tenure;
            $restore_data['monthly_installment'] = round($application->calculateMonthlyInstallment(), 2);
            $restore_data['total_payable'] = round($application->calculateTotalPayable(), 2);
        }

        $old_data = $application->toArray();
        $application->update($restore_data);

        AuditLog::create([
            'action' => 'application_restored_by_borrower',
            'borrower_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $application->toArray(),
            'notes' => 'Restored from canncelld status by borrower'
        ]);

        return $application->fresh();
    }

    /**
     * Restore a loan application by administrator.
     *
     * @param LoanApplication $application The loan application to be restored.
     * @param array $data The data to be updated.
     * @param User $admin The administrator performing the action.
     *
     * @throws \Exception If the current user is not an administrator, or if the application is not in the cancelled or rejected status, or if the application has an active loan account.
     */
    public function restoreByAdmin(LoanApplication $application, array $data, User $admin): void
    {
        if ($admin->isAdmin()) {
            throw new \Exception('Only administrator can perform this action');
        }

        $restorable_statuses = ['cancelled', 'rejected'];
        if (!in_array($application->status, $restorable_statuses)) {
            throw new \Exception("You cannot restore applications in {$application->status} status.");
        }

        if ($application->loanAccount && $application->loanAccount->status == 'active') {
            throw new \Exception("You cannot restore applications with active loan account.");
        }

        $target_status = $data['status'];
        $update_data = [
            'status' => $target_status,
            'closed_at' => null,
            'rejection_reason' => null,
        ];

        if (isset($data['update_amount'])) $update_data['amount'] = $data['amount'];
        if (isset($data['update_tenure'])) $update_data['tenure'] = $data['tenure'];
        if (isset($data['update_purpose'])) $update_data['purpose'] = $data['purpose'];
        if (isset($data['update_bank_branch'])) $update_data['bank_branch'] = $data['bank_branch'];

        if (isset($update_data['amount']) || isset($update_data['tenure'])) {
            $application->fill($update_data);
            $update_data['monthly_installment'] = round($application->calculateMonthlyInstallment(), 2);
            $update_data['total_payable'] = round($application->calculateTotalPayable(), 2);
        }

        if ($target_status === 'draft') {
            $update_data['application_ref'] = 'DRAFT-' . Str::random(10);
            $update_data['assigned_officer_id'] = null;
            $update_data['submitted_at'] = null;
            $update_data['approved_at'] = null;
            $update_data['disbursed_at'] = null;
            $update_data['reviewed_at'] = null;
            $update_data['closed_at'] = null;
        } elseif ($target_status === 'submitted') {
            $update_data['submitted_at'] = Carbon::now();
            $update_data['application_ref'] = $application->generateApplicationRef();
        } else if ($target_status === 'under_review') {
            $update_data['submitted_at'] = Carbon::now();
            $update_data['application_ref'] = $application->generateApplicationRef();
            $update_data['assigned_officer_id'] = $data['assigned_officer_id'] ?? $application->assigned_officer_id;
        }

        $old_data = $application->toArray();
        $application->updae($update_data);

        AuditLog::create([
            'action' => 'application_restored_by_admin',
            'user_id' => $admin->id,
            'loan_application_id' => $application->id,
            'old_data' => $old_data,
            'new_data' => $application->toArray(),
        ]);
    }

    /**
     * Validate the required (personal, employment, financial) fields for a loan application.
     *
     * @throws \Exception If the borrower record is not found or if the required fields are missing.
     */
    private function validateApplicationData(LoanApplication $application): void
    {
        $borrower = $application->borrower;
        if (!$borrower) {
            throw new \Exception("Borrower record not found for this application.");
        }

        $missing = [];

        foreach (self::REQUIRED_PERSONAL_DETAILS as $field) {
            if (empty($application->application_data['personal_info'][$field])) {
                $missing[] = "personal.{$field}";
            }
        }

        foreach (self::REQUIRED_EMPLOYMENT_DETAILS as $field) {
            if (empty($application->application_data['employment_info'][$field])) {
                $missing[] = "employment.{$field}";
            }
        }

        foreach (self::REQUIRED_FINANCIAL_DETAILS as $field) {
            if (empty($application->application_data['financial_info'][$field])) {
                $missing[] = "financial.{$field}";
            }
        }

        if (!empty($missing)) {
            throw new \Exception("Incomplete borrower data: " . implode(', ', $missing));
        }

    }

    /**
     * Validates that all required documents for the loan application have been uploaded.
     *
     * @throws \Exception If any required documents are missing.
     */
    private function validateApplicationDocuments(LoanApplication $application): void
    {
        $product = $application->loanProduct;
        if (!$product) {
            throw new \Exception("Loan product is not associated with this application.");
        }

        $required_documents = $product->required_documents ?? [];
        if (empty($required_documents)) {
            return;
        }

        if (!$application->relationLoaded('documents')) {
            $application->load('documents');
        }

        $uploaded_documents = $application->documents->pluck('document_type')->toArray();
        $missing = array_diff($required_documents, $uploaded_documents);

        if (!empty($missing)) {
            throw new \Exception("Missing required documents. Please upload: " . implode(', ', $missing));
        }

        $verified_documents = $application->documents->where('is_verified', true)->pluck('document_type')->toArray();
        $unverified_documents = array_diff($required_documents, $verified_documents);

        if (!empty($unverified_documents)) {
            throw new \Exception("Please, wait for staff to verify the following documents: " . implode(', ', $unverified_documents));
        }
    }

    /**
     * Calculates an initial review score based on the borrower's employment duration and debt ratio.
     * The score is based on the following:
     * - Employment duration: 12+ months => +10, 6-11 months => +5
     * - Debt ratio: < 30% => +15, 30%-50% => +5, 50%-100% => -10
     * - Amount ratio: < 50% => +15, 50%-100% => +5, 100%+ = -10
     * The score is fanilized at 0 and 100.
     *
     * @param LoanApplication $application The loan application to calculate the score for.
    * @return int The calculated review score.
     */
    private function calculateIniitialReviewScore(LoanApplication $application): int
    {
        $score = self::INITIAL_REVIEW_SCORE;
        $borrower = $application->borrower;

        if ($borrower) {
            if ($borrower->employment_duration > 12) {
                $score += 10;
            } else if ($borrower->employment_duration > 6) {
                $score += 5;
            }

            if ($borrower->monthly_income > 0) {
                $debt_ratio = ($borrower->toatal_debt / $borrower->monthly_income) * 100;

                if ($debt_ratio < self::DEBT_THRESHOLDS[0]) $score += 15;
                elseif ($debt_ratio < self::DEBT_THRESHOLDS[1]) $score += 5;
                else $score -= 10;

                $amount_ratio = ($application->amount / $borrower->monthly_income) * 100;

                if ($amount_ratio < self::AMOUNT_THRESHOLDS[0]) $score += 15;
                elseif ($amount_ratio < self::AMOUNT_THRESHOLDS[1]) $score += 5;
                else $score -= 10;
            }
        }

        return max(0, min(100, $score));
    }
}
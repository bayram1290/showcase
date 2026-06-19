<?php

namespace App\Contracts\Services;

use App\DataTransferObjects\ApprovalData;
use App\Models\LoanApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApprovalWorkflowServiceInterface
{
    /**
     * Approve loan application
     *
     * @param ApprovalData $data
     * @returnLoanApplication
     */
    public function approve(ApprovalData $data): LoanApplication;

    /**
     * Reject loan application
     *
     * @param ApprovalData $data
     * @returnLoanApplication
     */
    public function reject(ApprovalData $data): LoanApplication;

    /**
     * Get pending loan applications
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPendingApplications(array $filters = []): LengthAwarePaginator;
}
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
     * @param \App\DataTransferObjects\ApprovalData $data
     * @return \App\Models\LoanApplication
     */
    public function approve(ApprovalData $data): LoanApplication;

    /**
     * Reject loan application
     *
     * @param \App\DataTransferObjects\ApprovalData $data
     * @return \App\Models\LoanApplication
     */
    public function reject(ApprovalData $data): LoanApplication;

    /**
     * Get pending loan applications
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPendingApplications(array $filters = []): LengthAwarePaginator;
}
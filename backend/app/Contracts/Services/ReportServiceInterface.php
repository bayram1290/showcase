<?php

namespace App\Contracts\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

use App\DataTransferObjects\ReportFilterData;
use App\Models\User;

interface ReportServiceInterface
{
    /**
     * Get dashboard metrics
     *
     * @param ReportFilterData $filterData
     * @param User $user
     * @return array
    */
    public function getDashboardMetrics(
        ReportFilterData $filterData,
        User $user
    ): array;

    /**
     * Get approved loans metrics
     *
     * @param ReportFilterData $filterData
     * @param User $user
     * @return LengthAwarePaginator|null
     */
    public function getApprovedLoans(
        ReportFilterData $filterData,
        User $user
    ): LengthAwarePaginator;

    /**
     * Get NPA loans metrics
     *
     * @param ReportFilterData $filterData
     * @param User $user
     * @return LengthAwarePaginator|null
     */
    public function getNpaLoans(
        ReportFilterData $filterData,
        User $user
    ): LengthAwarePaginator;

    /**
     * Export approved loans
     *
     * @param ReportFilterData $filterData
     * @param User $user
     * @return Collection|null
     */
    public function exportApprovedLoans(
        ReportFilterData $filterData,
        User $user
    ): Collection|null;
}
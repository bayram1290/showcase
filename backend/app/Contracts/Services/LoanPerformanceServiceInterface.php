<?php

namespace App\Contracts\Services;

use App\Models\User;
use App\Models\Borrower;
use App\Models\LoanAccount;

interface LoanPerformanceServiceInterface
{

    /**
     * Get performance data for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account for which to retrieve the performance data.
     * @param User|Borrower $user The user or borrower associated with the loan account.
     * @param bool $includeChart (Optional) Whether to include the performance chart in the returned data.
     * @return array The performance data for the loan account, including loan details, payment history, and (optionally) the performance chart.
     */
    public function getPerformanceData(
        LoanAccount $loanAccount,
        User|Borrower $user,
        bool $includeChart = false
    ): array;
}
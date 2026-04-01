<?php

namespace App\Services;

use App\Models\Borrower;

class BorrowerDashboardService
{
    public function getFinancialSummary(Borrower $borrower): array
    {
        return [
            'total_applications' => $borrower->loanApplications()->count(),
            'active_loans' => $borrower->loanAccounts()->active()->count(),
            'total_borrowed' => $borrower->loanAccounts()->sum('disbursed_amount'),
            'outstanding_balance' => $borrower->loanAccounts()->sum('outstanding_balance'),
            'debt_to_income_ratio' => $borrower->getDebtToIncomeRatio()
        ];
    }
}
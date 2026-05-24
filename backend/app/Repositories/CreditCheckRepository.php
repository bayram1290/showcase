<?php

namespace App\Repositories;

use App\Contracts\Repositories\CreditCheckRepositoryInterface;
use App\Models\CreditCheck;

class CreditCheckRepository implements CreditCheckRepositoryInterface
{
    /**
     * Create a new credit check.
     *
     * @param array $data The credit check data to create.
     * @return array The newly created credit check.
     */
    public function create(array $data): array
    {
        $credit_check = CreditCheck::create($data);
        return $credit_check->only([
            'id',
            'credit_score',
            'debt_to_income_ratio',
            'credit_report_data',
            'checked_by',
            'remarks',
        ]);
    }

    /**
     * Finds the latest credit check by loan application ID.
     *
     * @param int $loanApplicationId The loan application ID to find the credit check for.
     * @return \App\Models\CreditCheck|null The found credit check or null if not found.
     */
    public function findLatestByLoanApplicationId(int $loanApplicationId): ?CreditCheck
    {
        return CreditCheck::where('loan_application_id', $loanApplicationId)->latest()->first();
    }
}
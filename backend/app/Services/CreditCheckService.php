<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Contracts\Repositories\CreditCheckRepositoryInterface;
use App\Contracts\Services\CreditCheckServiceInterface;
use App\DataTransferObjects\CreditCheckData;
use App\Models\CreditCheck;
use App\Models\LoanApplication;
use Carbon\Carbon;

class CreditCheckService implements CreditCheckServiceInterface
{
    protected CreditCheckRepositoryInterface $repository;

    const DEFAULT_SCORE_INTERNAL = 500;

    public function __construct(
        CreditCheckRepositoryInterface $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Calculates the internal credit score for a loan application.
     *
     * This function will calculate the internal credit score based on
     *  => the borrower's income,
     *  => employment duration,
     *  => debt to income ratio,
     *  => active application count,
     *  => borrower's age,
     *  => and loan amount ratio.
     *
     * The score will be adjusted based on the following rules:
     * - Income above 5000: +150
     * - Income above 3000: +100
     * - Income above 1500: +50
     * - Employment duration above 24 months: +100
     * - Employment duration above 12 months: +50
     * - Employment duration above 6 months: +20
     * - Debt to income ratio below 20: +100
     * - Debt to income ratio below 40: +50
     * - Debt to income ratio above 60: -100
     * - Active application count above 2: -50
     * - Age between 25 and 50: +50
     * - Age outside of 25 to 50: -50
     * - Loan amount ratio above 300: -50
     * - Loan amount ratio below 100: +50
     *
     * The final score will be capped between 0 and 1000.
     *
     * @param CreditCheckData $data The data to calculate the internal credit score with.
     * @return array The calculated internal credit score as an array.
     */
    public function calculateInternalScore(CreditCheckData $data): array
    {
        $application = LoanApplication::with('borrower')->findOrFail($data->loanApplicationId);
        $borrower = $application->borrower;

        $score = self::DEFAULT_SCORE_INTERNAL;
        $income = (double) $borrower->monthly_income ?? 0;

        if ($income > 5000) $score += 150;
        else if($income > 3000) $score += 100;
        else if($income > 1500) $score += 50;

        $duration = $borrower->employment_duration ?? 0;
        if ($duration > 24) $score += 100;
        else if($duration > 12) $score += 50;
        else if($duration > 6) $score += 20;

        $dti = $borrower->getDebtToIncomeRatio();
        if ($dti < 20) $score += 100;
        else if($dti < 40) $score += 50;
        else if($dti > 60) $score -= 100;

        $active_applications_count = $borrower->loanApplications()->whereIn('status', ['under_review', 'approved', 'disbursed'])->count();
        if ($active_applications_count > 2) $score -= 50;

        $age = $borrower->getAge();
        if ($age >= 25 && $age <= 50) $score += 50;
        else if ($age > 50 || $age < 25) $score -= 50;

        $loan_amount_ratio = ($application->amount/max($income, 1)) * 100;
        if ($loan_amount_ratio > 300) $score -= 50;
        else if($loan_amount_ratio < 100) $score += 50;

        $score = max(0, min(1000, $score));

        $report_data = [
            'borrower_income' => $income,
            'borrower_employment_duration' => $duration,
            'debt_to_income_ratio' => $dti,
            'active_applications_count' => $active_applications_count,
            'borrower_age' => $age,
            'loan_amount_ratio' => $loan_amount_ratio,
        ];

        $remarks = null;
        if (isset($data->remarks)) {
            $remarks = $data->remarks;
        }

        $existing = CreditCheck::where('loan_application_id', $application->id)
                        ->whereDate('created_at', Carbon::today())
                        ->where('credit_score', $score)
                        ->where('debt_to_income_ratio', $dti)
                        ->where('remarks', $remarks)
                        ->first();

        if ($existing) {
            throw new \Exception('A credit check with identical data already exists for this application today. No new record created.');
        }

        return DB::transaction(function () use ($application, $score, $dti, $report_data, $data) {
            return $this->repository->create([
                'loan_application_id' => $application->id,
                'credit_score' => $score,
                'debt_to_income_ratio' => $dti,
                'credit_report_data' => json_encode($report_data),
                'checked_by' => $data->checkedByUserId,
                'remarks' => $remarks ?? null
            ]);
        });
    }

    /**
     * Fetches an external credit score from a credit bureau.
     *
     * FYI: This is a fake function For demonstration purposes, a fake credit score is generated based on the given SSN.
     * The returned array contains the following keys:
     * - score: the external credit score
     * - rating: the rating based on the external credit score
     * - report_date: the date the report was generated
     * - bureau: the name of the credit bureau
     *
     * @param string $ssn The SSN to fetch the external credit score for.
     * @return array The external credit score data.
     */
    public function fetchExternalScore(string $ssn): array
    {
        $hash = crc32($ssn) % 100;
        $external_score = 300 + $hash * 7;
        return [
            'score' => $external_score,
            'rating' => $this->getRating($external_score),
            'report_date' => Carbon::now()->toDateString(),
            'bureau' => 'Sample_Credit_Bureau',
        ];
    }

    public function getForApplication(int $loanApplicationId): ?CreditCheck
    {
        return $this->repository->findLatestByLoanApplicationId($loanApplicationId);
    }

    private function getRating(int $score): string
    {
        return match (true) {
            $score >= 750 => 'Excellent',
            $score >= 700 => 'Good',
            $score >= 650 => 'Fair',
            $score >= 600 => 'Insufficient',
            default => 'None'
        };
    }
}
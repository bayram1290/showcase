<?php

namespace App\Services;

use App\Models\User;
use App\Models\Borrower;
use App\Models\LoanAccount;
use App\Contracts\Services\LoanPerformanceServiceInterface;
use Carbon\Carbon;

class LoanPerformanceService implements LoanPerformanceServiceInterface
{
    private const INS_STATUS = ['paid', 'pending', 'partial', 'overdue'];

    public function getPerformanceData(LoanAccount $loanAccount, User|Borrower $user, bool $includeChart = false): array
    {
        $loanAccount->loadMissing(['loanApplication', 'installments']);

        return [
            'summary' => $this->getPerformanceSummary($loanAccount, $user),
            'installments' => $this->getInstallments($loanAccount, $user),
            'payments' => $this->getPaymentHistory($loanAccount),
            'chart' => $includeChart ? $this->getChartData($loanAccount) : [],
            'late_fee_projection' => $this->getLateFeeProjection($loanAccount, $user),
        ];
    }

    // Helper functions

    /**
    * Get a performance summary for a loan account tailored to a borrower or user.
    * Returns an associative array containing key performance metrics and formatted dates.
    *
    * Notes:
    * next_due_date is derived from the first installment with status self::INS_STATUS[1], formatted or null if none.
    *
    * @param LoanAccount $loanAccount The loan account to summarise (includes relations: loanApplication, installments, assignedOfficer).
    * @param User|Borrower $user The requesting user or borrower; used to include supervisor-only fields when the user is a supervisor.
    * @return array<string,mixed> (
    *   - account_number: string\n
    *   - disbursed_amount: string (formatted number with 2 decimals)
    *   - outstanding_balance: string (formatted number with 2 decimals)
    *   - monthly_installment: string (formatted number with 2 decimals)
    *   - interest_rate: mixed (as stored on loan application)
    *   - tenure: int
    *   - installments_paid_count: int
    *   - installments_left_count: int
    *   - status: mixed (loan account status)
    *   - next_due_date: string|null (formatted "F j, Y")
    *   - disbursement_date: string|null (formatted "F j, Y")
    *   - branch: mixed (loan application / branch info)
    *   - loan_product: string
    *   - repaid_percentage: float (percentage rounded to 2 decimals)
    *   - Supervisor-only keys (added when $user is a User with role === 'supervisor'):
    *   - assigned_officer: string (first and last name)
    *   - closed_at: string|null (formatted "F j, Y")
    *   - days_since_last_payment: int|null)
    */
    private function getPerformanceSummary(LoanAccount $loanAccount, User|Borrower $user): array
    {
        $loan_application = $loanAccount->loanApplication;
        $installments = $loanAccount->installments;

        $paid_count = $installments->where('status', self::INS_STATUS[0])->count();
        $left_count = $loan_application->tenure - $paid_count;

        $disbursed_amount = $loanAccount->disbursed_amount;
        $outstanding_balance = $loanAccount->outstanding_balance;
        $next_due_date = $installments->where('status', self::INS_STATUS[1])->sortBy('due_date')->first()?->due_date;
        $next_due_date = $next_due_date ? Carbon::parse($next_due_date)->format('F j, Y') : null;
        $disbursement_date = $loan_application->disbursed_at ? Carbon::parse($loan_application->disbursed_at)->format('F j, Y') : null;
        $repaid_percentage = $disbursed_amount > 0 ?
            round((($disbursed_amount - $outstanding_balance) / $disbursed_amount) * 100, 2)
            : 0;

        $summary = [
            'account_number' => $loanAccount->account_number,
            'disbursed_amount' => number_format((float) $disbursed_amount, 2),
            'outstanding_balance' => number_format((float) $outstanding_balance, 2),
            'monthly_installment' => number_format($loan_application->monthly_installment, 2),
            'interest_rate' => $loan_application->interest_rate,
            'tenure' => $loan_application->tenure,
            'installments_paid_count' => $paid_count,
            'installments_left_count' => $left_count,
            'status' => $loanAccount->status,
            'next_due_date' => $next_due_date,
            'disbursement_date' => $disbursement_date,
            'loan_product' => $loan_application->loanProduct->name,
            'application_reference_number' => $loan_application->reference_number,
            'repaid_percentage' => $repaid_percentage,
        ];

        if ($user instanceof User && $user->isSupervisor()) {
            $officer = $loan_application->assignedOfficer;
            $last_payment = $installments->whereNotNull('paid_date')->sortByDesc('paid_date')?->first();

            $summary['assigned_officer'] = $officer->first_name . ' ' . $officer->last_name;
            $summary['closed_at'] = $loanAccount->closed_at ? Carbon::parse($loanAccount->closed_at)->format('F j, Y') : null;
            $summary['days_since_last_payment'] = $last_payment ? Carbon::parse($last_payment->paid_date)->diffForHumans() : 'No payment yet';
            $summary['branch'] = $loan_application->bankBranch->only([
                'name',
                'code',
                'address',
                'district',
                'phones',
                'fax',
                'is_headquarters',
            ]);
        }

        return $summary;
    }

    /**
     * Get the array of the installments for a loan account.
     *
     * @param LoanAccount $loanAccount The loan account object.
     * @param User|Borrower $user The user object or borrower object.
     * @return array The installments as an associative array.
     */
    private function getInstallments(LoanAccount $loanAccount, User|Borrower $user): array
    {
        $data = [];
        $installments = $loanAccount->installments->sortBy('due_date');

        foreach ($installments as $installment) {
            $item = [
                'installment_number' => $installment->installment_number,
                'due_date' => $installment->due_date ? Carbon::parse($installment->due_date)->format('F j, Y') : null,
                'due_amount' => $installment->due_amount,
                'status' => $installment->status,
                'paid_amount' => $installment->paid_amount ? number_format((float) $installment->paid_amount, 2) : null,
                'paid_date' => $installment->paid_date ? Carbon::parse($installment->paid_date)->format('F j, Y') : null,
                'late_fee' => number_format((float) $installment->late_fee, 2),
            ];

            if ($user instanceof User && $user->isSupervisor()) {
                $item['principal_amount'] = number_format($installment->principal_amount, 2);
                $item['interest_amount'] = number_format($installment->interest_amount, 2);

                if (in_array($installment->status, array_slice(self::INS_STATUS, 1, 2))) {
                    $min_fee = round($installment->due_amount * 0.1, 2);
                    $max_fee = round($installment->due_amount * 0.15, 2);
                    $item['projected_late_fee_min'] = $min_fee;
                    $item['projected_late_fee_max'] = $max_fee;
                }
            } else if (in_array($installment->status, array_slice(self::INS_STATUS, 1, 2))) {
                $item['projected_late_fee'] = number_format((float) $installment->due_amount * 0.1, 2);
            }

            $data[] = $item;
        }

        return $data;
    }

    /**
     * Build a chronological payment history for a loan account.
     *
     * Filter the account's installments to those with paid_amount > 0, sorts them by paid_date
     * descending, and returns an array of records with formatted dates and amounts.
     *
     * @param LoanAccount $loanAccount
     * @return array<int, array(
     *  - installment_number: int,
     *  - paid_date: ?string,
     *  - paid_amount: string,
     *  - late_fee_paid: ?string)
     * >
     * */
    private function getPaymentHistory(LoanAccount $loanAccount): array
    {
        $paid_installments = $loanAccount->installments->filter(fn ($i) => $i->paid_amount > 0)
                             ->sortByDesc('paid_date');

        return $paid_installments->map(function ($i) {
            return [
                'installment_number' => $i->installment_number,
                'paid_date' => $i->paid_date ? Carbon::parse($i->paid_date)->format('F j, Y') : null,
                'paid_amount' => number_format((float) $i->paid_amount, 2),
                'late_fee_paid' => $i->late_fee > 0 ? number_format((float) $i->late_fee, 2) : null

            ];
        })->values()->toArray();
    }

    /**
     * Generate amortization-style chart data for a loan account.
     *
     * Produce an ordered array of balance points (date, balance) starting at disbursement and
     * stepping through each installment due date.
     * Case-1: Handle zero-interest special case (linear principal decline) and
     * Case-2: Use standard amortization using the loan's interest_rate and tenure.
     *
     * @param LoanAccount $loanAccount
     * @return array<int, array(
     *  - date: ?string,
     *  - balance: float)
     * >
     */
    private function getChartData(LoanAccount $loanAccount): array
    {
        $loan_application = $loanAccount->loanApplication;
        $principal = $loan_application->amount;
        $monthly_rate = ($loan_application->interest_rate / 100) / 12;
        $months = $loan_application->tenure;
        $disbursement_date = $loan_application->disbursed_at ? Carbon::parse($loan_application->disbursed_at)->format('F j, Y') : null;
        $installments = $loanAccount->installments->sortBy('due_date');

        // For special case where zero interest
        if ($monthly_rate == 0) {
            $step = $principal / $months;
            $balance = $principal;
            $data = [
                [
                    'date' => $disbursement_date,
                    'balance' => round($balance, 2),
                ]
            ];
            foreach ($installments as $installment) {
                $balance -= $step;
                $data[] = [
                    'date' => $installment->due_date ? Carbon::parse($installment->due_date)->format('F j, Y') : null,
                    'balance' => round(max(0, $balance), 2),
                ];
            }

            return $data;
        }

        // Standard amorization
        $factor = pow(1 + $monthly_rate, $months);
        $denominator = $factor - 1;
        $data = [
            [
                'date' => $disbursement_date,
                'balance' => round($principal, 2),
            ]
        ];
        $i = 1;
        foreach ($installments as $installment) {
            $balance = $principal * (($factor - pow(1 + $monthly_rate, $i)) / $denominator);
            $data[] = [
                'date' => $installment->due_date ? Carbon::parse($installment->due_date)->format('F j, Y') : null,
                'balance' => round(max(0, $balance), 2),
            ];
            $i++;
        }

        return $data;
    }

    /**
     * Project late-fee exposure for a loan account, optionally including supervisor-only details.
     *
     * Calculate incurred overdue fees, projects potential future late fees for upcoming installments
     * (min and max scenarios), and builds per-installment details. For supervisor users additional
     * aggregated fields and impact/risk metrics are included.
     *
     * Notes:
     * min projection uses 10% of due_amount, max uses 15%.
     * impact_percent is total_min divided by outstanding_balance (0 if outstanding_balance == 0).
     *
     * @param LoanAccount $loanAccount
     * @param User|Borrower $user
     * @return array<string,mixed>(
     *  - overdue_fees_incurred: float
     *  - potential_fees_if_missed: float
     *  - savings_if_paid_on_time: float
     *  - details: array<int, array> If the user is a supervisor:(
     *      - potential_fees_min: float
     *      - potential_fees_max: float
     *      - total_potential_min: float
     *      - total_potential_max: float
     *      - impact_percent: float
     *      - risk_level: string ('Low'|'Medium'|'High'|'Critical')
     *  )
     */
    private function getLateFeeProjection(LoanAccount $loanAccount, User|Borrower $user): array
    {

        $installments = $loanAccount->installments;
        $overdue_fees = $installments->where('status', self::INS_STATUS[3])->sum('late_fee');
        $future_installmets = $installments->filter(fn($i) => in_array($i->status, array_slice(self::INS_STATUS, 1, 2)));

        $min_potential = 0;
        $max_potential = 0;
        $details = [];

        foreach ($future_installmets as $installment) {
            $min_fee = round($installment->due_amount * 0.1, 2);
            $max_fee = round($installment->due_amount * 0.15, 2);

            $min_potential += $min_fee;
            $max_potential += $max_fee;

            $detail = [
                'installment_number' => $installment->installment_number,
                'due_date' => $installment->due_date ? Carbon::parse($installment->due_date)->format('F j, Y') : null,
            ];

            if ($user instanceof User && $user->isSupervisor()) {
                $detail['projected_fee_min'] = $min_fee;
                $detail['projected_fee_max'] = $max_fee;
            } else {
                $detail['projected_fee'] = $min_fee;
            }

            $details[] = $detail;
        }

        $total_min = $overdue_fees - $min_potential;
        $remaining_balance = $loanAccount->outstanding_balance;
        $impact_percent = $remaining_balance > 0
                            ? round(($total_min / $remaining_balance) * 100, 2)
                            : 0;
        $risk_level = match(true) {
            $impact_percent > 15 => 'Critical',
            $impact_percent > 10 => 'High',
            $impact_percent > 5 => 'Medium',
            default => 'Low',
        };

        $projection = [
            'overdue_fees_incurred' => round($overdue_fees, 2),
            'potential_fees_if_missed' => round($min_potential, 2),
            'savings_if_paid_on_time' => round($max_potential, 2),
            'details' => $details,
        ];

        if ($user instanceof User && $user->isSupervisor()) {
            $projection['potential_fees_min'] = round($min_potential, 2);
            $projection['potential_fees_max'] = round($max_potential, 2);
            $projection['total_potential_min'] = round($total_min, 2);
            $projection['total_potential_max'] = round($overdue_fees + $max_potential, 2);
            $projection['impact_percent'] = $impact_percent;
            $projection['risk_level'] = $risk_level;
        }

        return $projection;
    }
}
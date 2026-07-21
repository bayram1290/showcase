<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Borrower;
use App\Models\LoanAccount;
use App\Models\Installment;

class ReceivablesPolicy
{
    public function before($user, $ability): ?bool
    {
        if ($user instanceOf Borrower) {
            return false;
        }

        return null;
    }

    /**
     * Determine if the user can view overdue installments
     *
     * @param User $user the user to check
     * @return bool
     */
    public function viewOverdue($user): bool
    {
        return in_array($user->role, ['collector', 'loan_officer', 'supervisor']);
    }

    /**
     * Determine if the user can negotiate a loan
     *
     * @param User $user the user to check
     * @param LoanAccount $loanAccount the loan account to check
     * @return bool
     */
    public function negotiate($user, LoanAccount $loanAccount): bool
    {
        if ($loanAccount->status === 'closed') {
        return false;
        }

        return in_array($user->role, ['collector', 'supervisor']);

    }

    /**
     * Determine if the user can waive a late fee
     *
     * @param User $user the user to check
     * @return bool
     */
    public function waiveLateFee($user): bool
    {
        return $user->role === 'collector';
    }

    /**
     * Determine if the user can send a reminder
     *
     * @param User $user the user to check
     * @return bool
     */
    public function sendReminder($user): bool
    {
        return in_array($user->role, ['loan_officer', 'supervisor']);
    }

    /**
     * Determine if the user can mark a loan as defaulted
     *
     * @param User $user the user to check
     * @param LoanAccount $loanAccount the loan account to check
     * @return bool
     */
    public function markDefault($user, LoanAccount $loanAccount): bool
    {
        if ($loanAccount->status !== 'active') {
            return false;
        }

        return $user->role === 'collector';
    }

    /**
     * Determine if the user can restore a defaulted loan
     *
     * @param User $user the user to check
     * @param LoanAccount $loanAccount the loan account to check
     * @return bool
     */
    public function restore($user, LoanAccount $loanAccount): bool
    {
        if ($loanAccount->status !== 'defaulted') {
            return false;
        }

        return $user->role === 'collector';
    }
}

<?php

namespace App\Policies;

use App\Models\Installment;
use App\Models\User;
use App\Models\Borrower;

// class RepaymentPolicy
class InstallmentPolicy
{
    /**
     * Check if the user can update the installment for repayment based on the user and installment provided.
     *
     * @param User $user The user performing the repayment.
     * @param Installment $installment The installment to be updated.
     * @return bool Returns true
     *  - if the user is a borrower and the borrower ID matches the loan application's borrower ID,
     *  - or if the user has the role of 'cashier'. Otherwise, returns false.
     */
    public function updateInstallmentForRepayment(User|Borrower $user, Installment $installment): bool
    {
        if ($user instanceof Borrower) {
            return $installment->loanAccount->loanApplication->borrower_id === $user->id;
        }

         if ($user instanceof User && $user->role === 'cashier') return true;

        return false;
    }
}

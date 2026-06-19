<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Borrower;
use App\Models\LoanAccount;

class LoanAccountPolicy
{

    public function viewPerformance(User|Borrower $user, LoanAccount $loanAccount): bool
    {
        if ($user instanceof Borrower) {
            return $loanAccount->loanApplication->borrower_id = $user->id;
        }

        if ($user instanceof User && in_array($user->role, ['loan_officer', 'supervisor'])) return true;

        return false;
    }
}

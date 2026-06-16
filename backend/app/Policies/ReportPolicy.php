<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view the Dashboard Report metrics
     *
     * @param User  $user
     * @return bool
     */
    public function viewDashboard(User $user): bool
    {
        return $user->role === 'manager' && $user->active();
    }

    /**
     * Determine whether the user can view the Approved Loans resource.
     *
     * @param User  $user
     * @return bool
     */
    public function viewApprovedLoans(User $user): bool
    {
        return in_array($user->role, ['manager', 'supervisor']) && $user->active();
    }

    /**
     * Determine whether the user can view the NPA resource.
     *
     * @param User  $user
     * @return bool
     */
    public function viewNpa(User $user): bool
    {
        return $user->role === 'manager' && $user->active();
    }

    /**
     * Deteermine whether the user can export approved reports
     *
     * @param User $user
     * @return bool
     */
    public function exportApprovedReports(User $user): bool
    {
        return $user->role === 'manager' && $user->active();
    }
}

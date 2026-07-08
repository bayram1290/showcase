<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Borrower;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoanApplicationPolicy
{
    public const LOAN_APPROVER_ROLES = ['loan_officer', 'supervisor'];

    /**
     * Create a new policy instance.
     */
    public function __construct() {}

    /**
     * Check if the user is authorized to view loan documents for a given loan application.
     *
     * @param mixed $user The user object.
     * @param LoanApplication $application The loan application object.
     * @return bool Returns true if the user is authorized, false otherwise.
     */
    public function viewLoanDocuments($user, LoanApplication $application): bool
    {

        if ($user instanceof Borrower) {
            return $user->id === $application->first()->borrower_id;
        }

        if ($user instanceof User) {
            return in_array($user->role, ['loan_officer', 'supervisor']) || ($user->id === $application->assigned_officer_id && $user->role === 'officer');
        }

        return false;
    }


    /**
     * Check if the borrower has permission to upload a document for a loan application.
     *
     * @param User|Borrower $user The user to check.
     * @param LoanApplication $application The loan application to upload the document for.
     * @return bool True if the user has permission to upload the document, false otherwise.
     */
    public function uploadDocument($user, LoanApplication $application): bool
    {
        if ($user instanceof Borrower) {
            return $application->borrower_id === $user->id;
        }

        return false;
    }

    /**
     * Check if a user can approve a loan application.
     *
     * @param  User $user
     * @param  LoanApplication $application
     * @return bool
     */
    public function approveApplication(User $user, LoanApplication $application): bool
    {
        if (!in_array($user->role, ['loan_officer', 'supervisor'])) return false;

        $role_traits = $this->getLevelTraits($user->role);

        if (empty($role_traits)) return false;

        $is_valid_amount = $application->amount >= $role_traits['min_amount'] && $application->amount <= $role_traits['max_amount'];
        $is_valid_tenure = $application->tenure >= $role_traits['min_term'] && $application->tenure <= $role_traits['max_term'];

        return $is_valid_amount && $is_valid_tenure;
    }

    /**
     * Check if a user can reject a loan application.
     *
     * @param  User $user
     * @param  LoanApplication $application
     * @return bool
     */
    public function rejectApplication(User $user, LoanApplication $application): bool
    {
        $required_roles = array_column(config('approval.levels'), "role") ?? self::LOAN_APPROVER_ROLES;
        return in_array($user->role, $required_roles);
    }

    private function getLevelTraits(string $role): array
    {
        $levels = Cache::remember('approval_levels', 86400, fn() => config('approval.levels'));

        foreach ($levels as $level) {
            if ($level['role'] === $role) {
                return $level;
            }
        }

        return [];
    }

    /**
     * Checks if the user has the appropriate role and if the loan application is in the approved status.
     *
     * @param User $user The user to check.
     * @param LoanApplication $application The loan application to check.
     * @return bool Returns `true` if the user can disburse the application, `false` otherwise.
     */
    public function disburseApplication(User $user, LoanApplication $application): bool
    {
        return in_array($user->role, ['loan_officer', 'supervisor']) && $application->status === 'approved';
    }
}

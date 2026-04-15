<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\DB;

class OfficerAssignmentService
{
    /**
     * Retrieves the ID of the officer with the least pending applications.
     * This method will return the ID of the officer with the least pending applications.
     * If no officers are found, this method will return null.
     *
     * @return int|null
     */
    public function getAvailableOfficers(): ?int
    {
        $officer = User::where('role', 'officer')
            ->where('is_active', true)
            ->leftJoin('loan_applications', function ($join) {
                $join->on('users.id', '=', 'loan_applications.assigned_officer_id')
                    ->whereIn('loan_applications.status', ['submitted', 'under_review']);
            })
            ->select('users.id', DB::raw('COUNT(loan_applications.id) as pending_applications'))
            ->groupBy('users.id')
            ->orderBy('pending_applications', 'ASC')
            ->first();

        return $officer?->id;
    }

    /**
     * An officer should be assigned if the application
     * - has no assigned officer,
     * - is in the submitted status,
     * - and is of type 'personal' or 'education'.
     *
     * @param LoanApplication $application The loan application to check.
     * @return bool True if an officer should be assigned, false otherwise.
     */
    public function shouldAssignOfficer(LoanApplication $application): bool
    {
        return is_null($application->assigned_officer_id) && $application->status === 'draft' && in_array($application->loan_type, ['personal', 'education']);
    }

    /**
     * Assign an available officer to a loan application if the application
     * meets the criteria specified in the shouldAssignOfficer method.
     *
     * @param LoanApplication $application The loan application to assign an officer to.
     * @return bool True if an officer was assigned, false otherwise.
     */
    public function assignOfficer(LoanApplication $application): void
    {

        if (! is_null($application->assigned_officer_id)) {
            throw new \Exception('Application already has an assigned officer');
        }

        if (!in_array($application->status, ['submitted', 'under_review'])) {
            throw new \Exception("Only submitted application can be assigned.");
        }

        $officer_id = $this->getAvailableOfficers();
        if (!$officer_id) {
            throw new \Exception("No available loan officer found.");
        }

        $application->update(['assigned_officer_id' => $officer_id]);
    }
}
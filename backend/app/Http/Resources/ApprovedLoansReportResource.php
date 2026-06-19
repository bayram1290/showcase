<?php

namespace App\Http\Resources;

use App\Models\BankBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class ApprovedLoansReportResource extends JsonResource
{
    private const LOAN_HIGH_AMOUNT = 50000;


    /**
     * Convert the object data to an array representation.
     *
     * Reconstruct the 'application_details' array with the following keys:
     * - reference => the value of the 'application_ref' property
     * - borrower => an array containing the 'name', 'phone', and 'email' of the borrower
     * - loan_product => the name of the loan product
     * - amount => the formatted amount of the loan
     * - approved_at => the formatted date of approval, or 'Not Approved' if not approved
     * - disbursed_since => the formatted time since disbursal, or 'Not Disbursed' if not disbursed
     * - status => the value of the 'status' property
     * - assigned_officer => the full name of the assigned officer, or null if not assigned
     * - bank_branch => Id of the bank branch
     * - is_high_value => 'Yes' if the amount is greater than or equal to the high value loan amount, or 'No' otherwise
     *
     * If an exception occurs during the conversion process, an array with an 'error' key and the exception message is returned.
     *
     * @param Request $request The request object.
     * @return array<string, mixed> The array representation of the object data.
     */
    public function toArray(Request $request): array
    {

        try {
            $approved_at = $this->approved_at;
            $days_since_approval = $this->disbursed_at ? Carbon::parse($this->disbursed_at)->diffForHumans() : null;
            $status = $this->status;

            $application_details = [
                'reference' => $this->application_ref,
                'borrower' => [
                    'name' => $this->borrower->getFullName(),
                    'phone' => $this->borrower->phone,
                    'email' => $this->borrower->email,
                ],
                'loan_product' => $this->loanProduct->name,
                'amount' => number_format($this->amount, 2),
                'approved_at' => $approved_at ? Carbon::parse($approved_at)->format('F j, Y') : 'Not Approved',
                'disbursed_since' => is_null($days_since_approval) ? 'Not Disbursed' : $days_since_approval,
                'status' => $status,
                'assigned_officer' => $this->assignedOfficer?->first_name . ' ' . $this->assignedOfficer?->last_name,
                'bank_branch' => $this->bankBranch ? BankBranch::find($this->bank_branch)->name : 'Not Assigned',
                'is_high_value' => $this->amount >= self::LOAN_HIGH_AMOUNT ? 'Yes' : 'No',
            ];

            return $application_details;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\BankBranch;
use Carbon\Carbon;

class NpaLoanReportResource extends JsonResource
{
    private const LOAN_INS_STATUS = ['overdue'];

    /**
     * Calculate
     *  - outstanding balance,
     *  - over due days,
     *  - risk category of a loan accounta and get the belated overdue installment from the loan account's installments collection,
     * and calculate the over due days based on the difference between the belated overdue's due date
     * and, then the current date. The risk category is determined based on the over due days.
     *
     * Reconstruct the resource data into an array with the following keys:
     * - reference: the value of the application_ref property
     * - borrower: an array containing the name, phone, and email of the borrower
     * - amount: the formatted amount of the loan
     * - disbursed_amount: the formatted disbursed amount of the loan
     * - outstanding_amount: the formatted outstanding balance of the loan
     * - overdue_days: the number of days the loan is overdue, or 'N/A' if no overdue installment is found
     * - risk_category: the risk category of the loan based on the over due days
     * - bank_branch: the name of the assigned bank branch, or 'Not Assigned' if not assigned
     * - created_at: the formatted creation date of the loan account
     *
     * @param Request $request The request object.
     * @return array<string, mixed> The array representation of the resource.
     */
    public function toArray(Request $request): array
    {
        $outstanding = $this->outstanding_balance ?? 0;
        $over_due_days = null;
        $risk_category = 'N/A';

        $belated_overdue = $this->loanAccount?->installments
                            ->where('status', self::LOAN_INS_STATUS[0])
                            ->sortBy('due_date')
                            ->first();

        if ($belated_overdue) {
            $over_due_days = Carbon::parse($belated_overdue->due_date)->diffInDays(Carbon::now());
            $risk_category = match (true) {
                $over_due_days > 100 => 'Critical',
                $over_due_days > 90 => 'High',
                $over_due_days > 30 => 'Medium',
                $over_due_days > 0 => 'Low',
                default => 'N/A',
            };
        }

        $npa_details = [
            'reference' => $this->application_ref,
            'borrower' => [
                'name' => $this->borrower->getFullName(),
                'phone' => $this->borrower->phone,
                'email' => $this->borrower->email,
            ],
            'amount' => number_format($this->amount, 2),
            'disbursed_amount' => number_format($this->disbursed_amount ?? 0, 2),
            'outstanding_amount' => number_format($outstanding, 2),
            'overdue_days' => $over_due_days ?? 'N/A',
            'risk_category' => $risk_category,
            'bank_branch' => $this->bankBranch ? BankBranch::find($this->bank_branch)->name : 'Not Assigned',
            'created_at' => Carbon::parse($this->created_at)->format('F j, Y'),
        ];

        return $npa_details;
    }
}

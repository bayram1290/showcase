<?php

namespace App\Http\Resources;

use App\Models\Borrower;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentRepaymentResource extends JsonResource
{
    protected $user;

    /**
     * Create a new resource instance.
     *
     * @param Installment $resource The installment resource.
     * @param User|null $user The user instance or null if not authenticated.
     * @return void
     */
    public function __construct(Installment $resource, $user = null)
    {
        parent::__construct($resource);
        $this->user = $user ?? request()->user();
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request The request instance.
     * @return array The repayment details.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'installment_number' => $this->installment_number,
            'due_amount' => number_format($this->due_amount, 2),
            'due_date' => Carbon::parse($this->due_date)->format('F j, Y'),
            'paid_date' => Carbon::parse($this->paid_date)->format('F j, Y'),
            'paid_amount' => number_format($this->paid_amount, 2),
            'status' => $this->status,
        ];

        if ($this->user instanceof Borrower) {
            return $data;
        }

        if ($this->user instanceof User) {
            $data = [
                ...$data,
                'principal_amount' => $this->principal_amount,
                'interest_amount' => $this->interest_amount,
                'late_fee' => $this->late_fee,
                'repayment_method_id' => $this->repayment_method_id,
                'loan_account_id' => $this->loan_account_id
                    ? LoanAccount::find($this->loan_account_id)->account_number
                    : "Loan Account Not Found for this loan account id: {$this->loan_account_id}"
            ];
        }

        return $data;
    }
}

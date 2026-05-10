<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\User;
use App\Models\Borrower;

use Carbon\Carbon;

class LoanAccountResource extends JsonResource
{
    protected User|Borrower $user;

    /**
     * @param mixed $resource The resource to be constructed.
     * @param User|Borrower $user The user object, optional.
     */
    public function __construct($resource, User|Borrower $user)
    {
        parent::__construct($resource);
        $this->user = $user;
    }

    /**
     * Convert the resource to an array.
     *
     * @param Request $request The HTTP request object.
     * @return array The converted resource data.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'account_number' => $this->account_number,
            'disbursed_amount' => $this->disbursed_amount,
            'outstanding_balance' => $this->outstanding_balance,
            'disbursement_date' => Carbon::parse($this->disbursement_date)?->toDateString(),
            'next_intallment_date' => Carbon::parse($this->next_installment_date)?->toDateString(),
            'status' => $this->status,
        ];

        if (in_array($this->user->role, ['loan_officer', 'supervisor'])) {
            $data = [
                ...$data,
                'id' => $this->id,
                'principal_paid' => $this->principal_paid,
                'installments_paid' => $this->installments_paid,
                'closed_at' => $this->closed_at ? Carbon::parse($this->closed_at)->toDateString() : null,
                'loan_application' => $this->whenLoaded('loanApplication', fn() => LoanApplicationResource::make($this->loanApplication)),
                'installments' => $this->whenLoaded('installments', fn() => InstallmentResource::collection($this->installments)),
            ];
        } elseif ($this->user->role == 'officer') {
            $data['installments'] = $this->whenLoaded('installments', fn() => InstallmentResource::collection($this->installments));
        }

        return $data;
    }
}

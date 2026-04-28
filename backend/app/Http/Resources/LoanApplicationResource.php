<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    /**
     * Transform the loan application resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->application_uuid,
            'reference' => $this->application_ref,
            'amount' => $this->amount,
            'status' => $this->status,
            'borrower' => [
                'name' => $this->borrower->getFullName(),
                'email' => $this->borrower->email,
            ],
            'loan_product' => $this->loanProduct->name,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'required_approval_level' => $this->getRequiredApprovalLevel(),
        ];
    }

    /**
     * Determine the required approval level based on the loan amount.
     *
     * @return string|null The required approval level or null if no level is found.
     */
    private function getRequiredApprovalLevel(): ?string
    {
        $user_levels = config('approval.levels');

        $levels = [];

        foreach ($user_levels as $level) {
            if ($this->amount <= $level['max_amount']) {
                $levels[] = $level['role'];
            }
        }

        return count($levels) > 0 ? implode(', ', $levels) : null;
    }
}

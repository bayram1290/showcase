<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date->toDateString(),
            'due_amount' => $this->due_amount,
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'paid_date' => $this->paid_date?->toDateString(),
            'paid_amount' => $this->paid_amount,
            'late_fee' => $this->late_fee,
            'status' => $this->status,
        ];
    }
}

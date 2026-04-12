<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateLoanApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:personal,mortgage,auto,business,education',
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|in:' . implode(',', config('helper.loan.tenure_options')),
            'purpose' => 'required|string',
            'bank_branch' => 'required|string|max:50',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type')) {
            $loan_type = match ($this->input('type')) {
                1 => 'personal',
                2 => 'business',
                3 => 'education',
                4 => 'auto',
                5 => 'mortgage',
                default => 0
            };

            if ($loan_type == 0) {
                throw new \Exception('Invalid loan type');
            }

            $this->merge(['type' => $loan_type]);
        }
    }
}

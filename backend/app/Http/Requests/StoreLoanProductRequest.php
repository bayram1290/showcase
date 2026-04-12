<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for storing a loan product
     *
     * @return array Validation rules
     */
    public function rules(): array
    {

        return [
            'name' => 'required|string|max:255|unique:loan_products,name',
            'description' => 'required|string|max:1000',
            'min_amount' => 'required|numeric|min:1',
            'max_amount' => 'required|numeric|gt:min_amount',
            'interest_rate' => 'required|decimal:2|min:0.01|max:100',
            'interest_type' => 'required|string|in:fixed,variable',
            'min_tenure' => 'required|integer|min:1',
            'max_tenure' => 'required|integer|gt:min_tenure|' . Rule::in(config('helper.loan.tenure_options')),
            'type' => 'required|string|' . strtolower(Rule::in(config('helper.loan.types'))),
            'eligibility_criteria' => 'nullable|array',
            'required_documents' => 'nullable|array',
            'processing_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'late_fee' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Prepares the request data for validation.
     * If the interest rate is a double, it will be formatted to 2 decimal places.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('interest_rate') && is_double($this->interest_rate)) {
            $this->merge(['interest_rate' => number_format($this->interest_rate, 2)]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'The loan product name has already been taken.',
            'max_amount.gt' => 'The maximum amount must be greater than the minimum amount.',
            'max_tenure.gte' => 'The maximum tenure must be greater than or equal to the minimum tenure.',
            'max_tenure.in' => 'The maximum tenure must be one of the following: ' . implode(', ', config('helper.loan.tenure_options')),
            'interest_rate.max' => 'The interest rate must be between 0.01 and 100.',
            'type.in' => 'The type must be one of the following: ' . implode(', ', config('helper.loan.types')),
        ];
    }
}

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
            'loan_product_id' => 'required|exists:loan_products,id',
            'amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|in:' . implode(',', config('helper.loan.tenure_options')),
            'purpose' => 'required|string',
            'bank_branch' => 'required|exists:bank_branches,id',
        ];
    }

    /**
     * Prepare the input data for validation.
     *
     * This function checks if the 'type' and 'bank_branch' fields are present in the input data and performs validation on them.
     * If the 'type' field is present, it maps the input value to the corresponding loan type and sets the 'type' field in the input data.
     * If the 'bank_branch' field is present and is numeric, it converts the input value to an integer and sets the 'bank_branch' field in the input data.
     * If the validation fails, an exception is thrown.
     *
     * @return void
     * @throws \Exception If the 'type' field is invalid or the 'bank_branch' field is not numeric.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('bank_branch')) {
            if (is_numeric($this->input('bank_branch'))) $this->merge(['bank_branch' => (int) $this->input('bank_branch')]);
            else throw new \Exception('Invalid bank branch');
        }
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestoreLoanApplicationRequest extends FormRequest
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
            'update_data' => 'nullable|array',
            'update_data.amount' => 'nullable|numeric|min:1',
            'update_data.tenure' => 'nullable|integer|in:' . implode(',', config('helper.loan.tenure_options')),
            'update_data.purpose' => 'nullable|string',
            'bank_branch' => 'nullable|string|max:50',
        ];
    }
}

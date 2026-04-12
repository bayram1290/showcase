<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminRestoreRequest extends FormRequest
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
            'target_status' => ['required', Rule::in(['draft', 'submitted', 'under_review'])],
            'assigned_officer_id' => 'nullable|exists:users,id',
            'restoration_reason' => 'nullable|string|max:500',
            'update_amount' => 'nullable|numeric|min:1',
            'update_tenure' => 'nullable|integer|in' . implode(',', config('helper.loan.tenure_options')),
            'update_purpose' => 'nullable|string|max:500',
            'skip_validation' => 'nullable|boolean',
        ];
    }
}

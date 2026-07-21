<?php

namespace App\Http\Requests\Receivables;

use Illuminate\Foundation\Http\FormRequest;

class OverdueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days_overdue' => 'nullable|integer|min:1',
            'loan_account_id' => 'nullable|exists:loan_accounts,id',
            'borrower_id' => 'nullable|exists:borrowers,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:due_date,days_overdue,amount',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Report policy
    }

    /**
     * Get the validation rules that apply to the report filter request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // 'filterType' => 'required|integer',
            'from_date' => 'nullable|date|before_or_equal:to_date|date_format:Y-m-d',
            'to_date' => 'nullable|date|after_or_equal:from_date|date_format:Y-m-d',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_date.before_or_equal' => 'The from date must be before or equal to the to date.',
            'to_date.after_or_equal' => 'The to date must be after or equal to the from date.',
        ];
    }
}

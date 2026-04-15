<?php

namespace App\Http\Requests;

use App\Models\LoanApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InternalCreditCheckRequest extends FormRequest
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
            'uuid' => 'required|string|exists:loan_applications,application_uuid',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    public function getLoanApplication(): LoanApplication
    {
        return LoanApplication::where('application_uuid', $this->uuid)->firstOrFail();
    }
}

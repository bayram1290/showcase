<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class MobileValidationRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                'string',
                'regex:/' . config('helper.mobile_format_verification') . '/',
            ],
        ];
    }

/**
 * Returns validation error messages
 *
 * @return array<string, string> an array of validation error messages
 */
    public function messages(): array
    {
        return [
            'mobile.required' => 'Mobile number is required',
            'mobile.regesx' => 'Invalid mobile number format. Valid format starts with 993 and ends with 8 valid digits',
        ];
    }

    /**
     * Prepare the request data for validation by removing any whitespace from the mobile number field
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge([
                'mobile' => preg_replace('/\s+/', '', $this->mobile),
            ]);
        }
    }

/**
 * Handle a failed validation attempt.
 *
 * @param \Illuminate\Contracts\Validation\Validator $validator
 *
 * @throws \Illuminate\Validation\ValidationException
 */
    protected function failedValidation(Validator $validator): never
    {
        throw new ValidationException($validator);
    }
}

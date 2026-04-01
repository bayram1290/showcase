<?php

namespace App\Http\Requests;

use App\Rules\ValidMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Log;

class CreateBorrowerRequest extends FormRequest
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

        \Illuminate\Support\Facades\Log::info($this->login);
        return [
            'login' => ['required', 'string', 'min:5', 'max:50', 'unique:borrowers,login', 'regex:/'. config('helper.auth.login_format') .'/'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/'. config('helper.auth.password_format') .'/'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:100', 'unique:borrowers,email'],
            'phone' => ['required', 'string', new ValidMobileNumber(), 'unique:borrowers,phone'],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'address' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'citizenship' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'monthly_income' => ['required', 'numeric', 'min:0', 'regex:/'  .config('helper.auth.monthly_income_format') . '/'] ,
            'employment_status' => ['required', Rule::in(config('helper.auth.employment_statuses'))],
            'employer_name' => ['required_if:employment_status,employed', 'string', 'max:100'],
            'employment_duration' => ['required_if:employment_status,employed, self-employed', 'nullable', 'integer', 'min:0'],
            'occupation' => 'nullable|string|max:100',
            'ssn' => ['required', 'string', 'unique:borrowers,ssn', 'regex:/'. config(key: 'helper.auth.ssn_format') .'/'],
            'government_id_number' => ['nullable', 'string', 'unique:borrowers,government_id_number'],
            'government_id_type' => ['required_with:government_id', 'nullable', Rule::in(config('helper.auth.government_id_types'))],
            'monthly_expenses' => ['required', 'numeric', 'min:0', 'regex:/'  .config('helper.auth.monthly_expenses_format') . '/'],
            'preferred_contact_method' => ['nullable', Rule::in(config('helper.auth.contact_methods'))],
            'marital_status' => ['nullable', Rule::in(config('helper.auth.marital_statuses'))],
            'dependents' => 'nullable|integer|min:0|max:20',
        ];
    }

    /**
     * Return the validation error messages for borrower registration form
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'Login can only contain letters, numbers, and underscores.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'date_of_birth.before' => 'You must be at least 18 years old to register.',
            'ssn.regex' => 'SSN must be exactly 9 digits.',
            'employer_name.required_if' => 'Employer name is required when employment status is employed.',
            'employment_duration.required_if' => 'Employment duration is required when employment status is employed or self-employed.',
        ];
    }

    /**
     * Prepare the request data for validation
     * - remove any whitespace from the phone number and SSN fields
     * - set default values for the preferred contact method and marital status fields if not present
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^\d]/', '', $this->phone)
            ]);
        }

        if ($this->has('ssn')) {
            $this->merge([
                'ssn' => preg_replace('/[^\d]/', '', $this->ssn),
            ]);
        }

        if (!$this->has('preferred_contact_method')) {
            $this->merge([
                'preferred_contact_method' => 'email',
            ]);
        }

        if (!$this->has('marital_status')) {
            $this->merge([
                'marital_status' => 'single',
            ]);
        }

        if ($this->has('gender')) {
            $gender = strtolower($this->gender);
            match ($gender) {
                'male' => $this->merge(['gender' => 'M']),
                'female' => $this->merge(['gender' => 'F']),
                default => $this->merge(['gender' => null]),
            };
        }
    }
}

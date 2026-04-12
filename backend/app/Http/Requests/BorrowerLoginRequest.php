<?php

namespace App\Http\Requests;

use App\Models\Borrower;
use Illuminate\Foundation\Http\FormRequest;

class BorrowerLoginRequest extends FormRequest
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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Please, enter your login',
            'password.required' => 'Password is required'
        ];
    }

    /**
     * Prepare the validation data for the request.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('login')) {
            $this->merge(['login' => trim(strtolower($this->input('login')))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Only perform this check if login is present (to avoid errors)
            if (!$this->filled('login')) {
                return;
            }

            $login = $this->input('login');

            // Attempt to find the borrower by login or email
            $borrower = Borrower::where('login', $login)
                ->orWhere('email', $login)
                ->first();

            if ($borrower && $borrower->is_blocked) {
                $validator->errors()->add(
                    'login',
                    'Your account is locked due to multiple failed login attempts. Please contact support.'
                );
            }

            // Optional: Check for pending verification
            if ($borrower && !$borrower->email_verified_at) {
                $validator->errors()->add(
                    'login',
                    'Your account is not yet verified. Please wait for verification (up to 24 business hours).'
                );
            }

            // Optional: Check if account is deactivated
            if ($borrower && !$borrower->is_active) {
                $validator->errors()->add(
                    'login',
                    'Your account has been deactivated. Please contact support.'
                );
            }
        });
    }
}

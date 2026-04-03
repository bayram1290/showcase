<?php

namespace App\Http\Requests;

use App\Rules\ValidMobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user_id = $this->route('user');

        return [
            'first_name' => 'sometimes|required|string|max:50',
            'last_name' => 'sometimes|required|string|max:50',
            'phone' => ['sometimes', 'nullable', 'string', new ValidMobileNumber()],
            'role' => ['sometimes', 'required', Rule::in(['loan_officer', 'moderator'])],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user_id)],
            'department' => 'sometimes|nullable|string',
            'employee_id' => ['sometimes', 'nullable', 'string', Rule::unique('users')->ignore($user_id)],
            'date_of_joining' => 'sometimes|nullable|date',
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirnmd'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role must be either loan_officer or moderator.',
            'email.unique' => 'This email is already taken.',
            'employee_id.unique' => 'This employee ID us already assigned.'
        ];
    }
}

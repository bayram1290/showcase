<?php

namespace App\Http\Requests\Receivables;

use App\Domain\Receivables\Enums\NegotiationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NegotiationRequest extends FormRequest
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
            'note' => 'required|string|min:10|max:1000',
            'type' => ['nullable', 'string', Rule::enum(NegotiationType::class)],
            'terms' => 'nullable|array',
            'accepted_amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after:today',
        ];
    }
}
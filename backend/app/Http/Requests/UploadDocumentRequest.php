<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
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
            'document_type' => 'required|string|max:50',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5128', // File size limit: 5 MB
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('product_id')) {
            throw new \Exception("Invalid loan application.");
        }

        if (!in_array($this->document_type, LoanProduct::find($this->input('product_id'))->required_documents) ) {
            throw new \Exception("Invalid document type.");
        }
    }
}

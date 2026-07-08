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


    /**
     * Prepare the validation for the request.
     *
     * Validate the request by checking the following conditions:
     * - The loan application is valid.
     * - The loan product is associated with the application.
     * - The document type is allowed for the loan product.
     *
     * @return void
     * @throws \Exception If the loan application is invalid, the loan product is not associated with the application, or the document type is not allowed for the loan product.
     */
    protected function prepareForValidation(): void
    {

        $application = $this->route('application');
        if (!$application) {
            throw new \Exception("Invalid loan application.");
        }

        $loan_product = $application->loanProduct;
        if (!$loan_product) {
            throw new \Exception("Loan product is not associated with this application.");
        }

        $allowed_types = $loan_product->required_documents ?? [];
        if (!in_array($this->document_type, $allowed_types)) {
            throw new \Exception("Document type is not allowed for this loan product.");
        }
    }
}

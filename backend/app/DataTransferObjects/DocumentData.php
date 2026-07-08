<?php

namespace App\DataTransferObjects;

use App\Http\Requests\UploadDocumentRequest;
use App\Models\LoanApplication;
use Illuminate\Http\UploadedFile;

final class DocumentData
{
    /**
     * Constructor (private) for the DocumentData class.
     *
     * @param LoanApplication $application The loan application object.
     * @param UploadedFile $file The uploaded file object.
     * @param string $documentType The type of the document.
     */
    private function __construct(
        public readonly LoanApplication $application,
        public readonly UploadedFile $file,
        public readonly string $documentType
    ) {}


    /**
     * Create an instance of the DocumentData class from the given request data.
     *
     * @param UploadDocumentRequest $request The request object.
     * @param LoanApplication $application The loan application object.
     * @return self The instance of the DocumentData class.
     */
    public static function fromRequest(
        UploadDocumentRequest $request,
        LoanApplication $application
    ) {
        $validated = $request->validated();

        return new self(
            application: $application,
            file: $validated['file'],
            documentType: $validated['document_type']
        );
    }

    /**
     * Convert the DocumentData object to an array representation.
     *
     * @return array The array representation of the DocumentData object.
     */
    public function toArray(): array
    {
        return [
            'loan_application_id' => $this->application->id,
            'file' => $this->file,
            'document_type' => $this->documentType,
        ];
    }
}
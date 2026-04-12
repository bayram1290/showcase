<?php

namespace App\Services;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    const UPLOAD_DOCUMENT_STATUSES = ['draft', 'submitted', 'under_review'];

    /**
     * Uploads a document for a given loan application.
     *
     * @throws \Exception If the application status is not draft, submitted, or under_review.
     * @throws \Exception If the document cannot be stored.
     *
     * @param LoanApplication $application The loan application for which the document is being uploaded.
     * @param UploadedFile $file The uploaded file.
     * @param string $documentType The type of document being uploaded.
     *
     * @return Document The uploaded document.
     */
    public function upload(LoanApplication $application, UploadedFile $file, string $documentType): Document
    {
        if (!in_array($application->status, self::UPLOAD_DOCUMENT_STATUSES)) {
            throw new \Exception("Documents can only be uploaded for applications in draft, submitted, or under review status.");
        }

        $file_size = $this->formatFileSize($file->getSize());

        $xtension = $file->getClientOriginalExtension();
        $file_name = Str::uuid() . '.' . $xtension;
        $path = "documents/$application->application_uuid/$documentType";
        $stored_path = $file->storeAs($path, $file_name, 'public');


        if (!$stored_path) {
            throw new \Exception("Failed to upload document.");
        }

        $document = Document::create([
            'loan_application_id' => $application->id,
            'document_type' => $documentType,
            'file_name' => $file_name,
            'file_path' => $path,
            'file_size' => $file_size,
            'mime_type' => $file->getClientMimeType(),
            'is_verified' => false,
         ]);

         return $document;
    }

    /**
     * Deletes a document from the database and storage.
     *
     * @throws \Exception If the document is verified.
     *
     * @param Document $document The document to be deleted.
     */
    public function delete(Document $document): void
    {
        if ($document->is_verified) {
            throw new \Exception("You cannot delete a verified document.");
        }

        if (Storage::disk('public')->exists($document->file_path . '/' . $document->file_name)) {
            Storage::disk('public')->delete($document->file_path . '/' . $document->file_name);

            $files = Storage::disk('public')->files($document->file_path);
            if (empty($files)) {
                Storage::disk('public')->deleteDirectory($document->file_path);
            }
        }

        $document->delete();
    }

    /**
     * Verify a document.
     *
     * @param Document $document The document to be verified.
     * @param User $staff The staff member who is verifying the document.
     * @param string $notes Optional notes about the verification.
     *
     * @throws \Exception If the document is already verified or if the staff member does not have permission to verify documents.
     */
    public function verify(Document $document, User $staff, ?string $notes = null): void {
        if ($document->is_verified) {
            throw new \Exception("You cannot verify a document that is already verified.");
        }

        if (!$staff->isLoanOfficer() && $staff->id !== $document->loanApplication->assigned_officer_id) {
            throw new \Exception("You do not have permission to verify this document.");
        }

        $document->update([
            'is_verified' => true,
            'verification_notes' => $notes,
            'verified_by' => $staff->id,
            'verified_at' => Carbon::now()
        ]);
    }

    private function formatFileSize(int $fileSizeInBytes): string
    {
        $size_in_bytes = $fileSizeInBytes;
        $size_in_kilobytes = $size_in_bytes / 1024;
        $size_in_megabytes = $size_in_bytes / 1024 / 1024;
        $size_in_gigabytes = $size_in_bytes / 1024 / 1024 / 1024;

        if ($size_in_gigabytes > 1) {
            return sprintf('%.2f GB', $size_in_gigabytes);
        }

        if ($size_in_megabytes > 1) {
            return sprintf('%.2f MB', $size_in_megabytes);
        }

        if ($size_in_kilobytes > 1) {
            return sprintf('%.2f KB', $size_in_kilobytes);
        }

        return sprintf('%d bytes', $size_in_bytes);
    }
}
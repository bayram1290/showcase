<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use App\Contracts\Services\DocumentServiceInterface;
use App\Jobs\GenerateThumbnailJob;
use App\Jobs\ScanDocumentJob;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\User;

use Carbon\Carbon;

class DocumentService implements DocumentServiceInterface
{
    const UPLOAD_STATUSES = ['draft', 'submitted', 'under_review'];

    /**
     * Upload a document for a loan application.
     *
     * - validate the application status,
     * - check for existing verified documents,
     * - generate a unique filename,
     * - store the file in storage,
     * - create a Document record,
     * - dispatch ScanDocumentJob and GenerateThumbnailJob (if applicable),
     * - and return the created Document object.
     *
     * @param LoanApplication $application The loan application.
     * @param UploadedFile $file The uploaded file.
     * @param string $documentType The document type.
     * @return Document The created Document object.
     * @throws \Exception If the application status is invalid, a verified document exists,
     * or file storage fails.
     */
    public function upload(LoanApplication $application, UploadedFile $file, string $documentType): Document
    {
        if (!in_array($application->status, self::UPLOAD_STATUSES)) {
            throw new \Exception("Documents can only be uploaded for applications in draft, submitted, or under review status.");
        }

        $document_query = Document::where('loan_application_id', $application->id)
            ->where('document_type', $documentType);

        $verified = clone $document_query;
        $verified = $verified->where('is_verified', 1)->orderByDesc('created_at')->exists();

        if ($verified) {
            throw new \Exception("A verified document of type $documentType already exists for this application. Action denied.");
        }

        $existing = $document_query->where('is_verified', false)->exists();
;
        if ($existing) {
            $existing->delete();
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $filepath = "documents/$application->application_uuid/$documentType";
        $document_details = [
            'original_file_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
            'file_name' => $filename,
            'file_path' => $filepath,
            'storage_path' => $file->storeAs($filepath, $filename, 'public'),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'is_verified' => false,
        ];

        if (!$document_details['storage_path']) {
            throw new \Exception("Failed to store document. Please try again or contact support.");
        }

        $document = Document::create(array_merge($document_details, [
            'loan_application_id' => $application->id,
            'document_type' => $documentType
        ]));

        ScanDocumentJob::dispatch($document); // scan for viruses

        if ($document->isImage()) {
            GenerateThumbnailJob::dispatch($document); // generate thumbnail if document is an image
        }

        return $document;
    }

    /**
     * Delete a document.
     *
     * Soft delete a document if it is not verified. Throws an exception if the document is verified.
     *
     * @param Document $document The document to delete.
     * @return void
     * @throws \Exception If the document is verified.
     */
    public function delete(Document $document): void
    {
        if ($document->isVerified()) {
            throw new \Exception("You cannot delete a verified document.");
        }

        $document->delete();
    }

    /**
     * Verify a document.
     *
     * Verify a document if it is not verified and not infected. Throws an exception if the document is verified.
     * Update the document record with the verification details.
     *
     * @param Document $document The document to verify.
     * @param User $user The user verifying the document.
     * @param string|null $notes (optional) The verification notes.
     * @return void
     * @throws \Exception If the document is verified, infected, or being scanned for viruses.
     */
    public function verify(Document $document, User $user, ?string $notes = null): void
    {
        if ($document->isVerified()) {
            throw new \Exception("You cannot verify a document that is already verified.");
        }

        if ($document->isInfected()) {
            throw new \Exception("You cannot verify a document that has been detected as infected.");
        }

        if ($document->isPendingScan()) {
            throw new \Exception("The document is currently in the process of being scanned for viruses. Please try again later.");
        }

        $document->update([
            'is_verified' => true,
            'verification_notes' => $notes,
            'verified_by' => $user->id,
            'verified_at' => Carbon::now()
        ]);
    }


    /**
     * List documents for a loan application.
     *
     * @param LoanApplication $application The loan application to list documents for.
     * @param array $filters (optional) The filters to apply to the query.
     * @return Collection The list of documents.
     */
    public function listDocuments(LoanApplication $application, array $filters = []): Collection
    {
        $query = $application->documents()->with('verifier');

        if (isset($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Generate a temporary URL for a document.
     *
     * Generate a temporary URL for a document that can be used to download the document for a limited time
     * (time is 15 minutes and contains the document UUID as a parameter)
     *
     * @param Document $document The document to generate the URL for.
     * @return string The temporary URL for the document.
     */
    public function generateTemporaryUrl(Document $document): string
    {
        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes(15),
            ['document' => $document->uuid]
        );
    }

    /**
     * Purge soft deleted documents.
     *
     * Purge soft deleted documents that are older than 6 months.
     * Delete the file from storage, the thumbnail from storage,
     * and the directory from storage if it is empty.
     * Force deletes the document record.
     *
     * @return int The number of deleted documents.
     */
    public function purgeSoftDeletedDocuments(): int
    {
        $six_months_ago = Carbon::now()->subMonths(6);

        $documents = Document::onlyTrashed()
                        ->where('deleted_at', '<=', $six_months_ago)
                        ->get();
        $cnt = 0;

        foreach ($documents as $document) {
            $full_path = $document->file_path . '/' . $document->file_name;

            // Delete the file from storage
            if (Storage::disk('public')->exists($full_path)) {
                Storage::disk('public')->delete($full_path);
            }

            // Delete the thumbnail
            if ($document->thumbnail_path && Storage::disk('public')->exists($document->thumbnail_path)) {
                Storage::disk('public')->delete($document->thumbnail_path);
            }

            // Delete the directory if it is empty
            $file_directory = $document->file_path;
            $is_dir_exists = Storage::disk('public')->exists($file_directory);
            $is_dir_empty = empty(Storage::disk('public')->files($file_directory));
            if ( $is_dir_exists && $is_dir_empty ) {
                Storage::disk('public')->deleteDirectory($file_directory);
            }

            $document->forceDelete();
            $cnt++;
        }

        return $cnt;
    }
}
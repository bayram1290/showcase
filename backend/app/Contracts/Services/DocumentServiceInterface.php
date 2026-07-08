<?php

namespace App\Contracts\Services;

use Illuminate\Http\UploadedFile;

use App\Models\LoanApplication;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;

interface DocumentServiceInterface
{
    /**
     * Upload a document for a loan application.
     *
     * @param LoanApplication $application
     * @param UploadedFile $file
     * @param string $documentType
     * @return Document
     * @throws \Exception
     */
    public function upload(
        LoanApplication $application,
        UploadedFile $file,
        string $documentType
    ): Document;

    /**
     * Soft delete a document (if unverified).
     *
     * @param Document $document
     * @return void
     * @throws \Exception
     */
    public function delete(
        Document $document
    ): void;

    /**
     * Verify a document (staff only).
     *
     * @param Document $document
     * @param User $user
     * @param string|null $notes
     * @return void
     * @throws \Exception
     */
    public function verify(
        Document $document,
        User $user,
        ?string $notes = null
    ): void;

    /**
     * List documents for an application (with optional filters).
     *
     * @param LoanApplication $application
     * @param array $filters
     * @return Collection
     */
    public function listDocuments(
        LoanApplication $application,
        array $filters = []
    ): Collection;

    /**
     * Generate a temporary signed URL for downloading a document.
     *
     * @param Document $document
     * @return string
     */
    public function generateTemporaryUrl(Document $document): string;

    /**
     * Permanently delete soft‑deleted documents older than 6 months.
     *
     * @return int Number of documents purged.
     */
    public function purgeSoftDeletedDocuments(): int;
}
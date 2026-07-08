<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Borrower;
use App\Models\Document;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{

    /**
     * Check if the user has permission to view the document.
     *
     * Check if the user is a borrower and if they are the borrower of the loan application associated with the document.
     * If the user is not a borrower, check if the user is an officer and if they are the assigned officer of the loan application.
     * If the user is not an officer, check if the user has a role of 'loan_officer' or 'supervisor'.
     *
     * @param User|Borrower $user The user to check.
     * @param Document $document The document to check.
     * @return bool True if the user has permission to view the document, false otherwise.
     */
    public function viewDocument($user, Document $document): bool
    {
        $application = $document->loanApplication;

        if ($user instanceof Borrower) {
            return $user->is($application->borrower);
        }

        if ($user instanceof User) {
            if ($user->role === 'officer') {
                return $user->id === $application->assigned_officer_id;
            }
            return in_array($user->role, ['loan_officer', 'supervisor']);
        }

        return false;
    }

    /**
     * Check if the user has permission to download the document by calling the `viewDocument` function.
     *
     * @param User|Borrower $user The user to check.
     * @param Document $document The document to check.
     * @return bool True if the user has permission to download the document, false otherwise.
     */
    public function downloadDocument($user, Document $document): bool
    {
        return $this->viewDocument($user, $document);
    }

    /**
     * Delete the document if the user has permission to do so.
     *
     * Check if the user is a borrower and if they are the borrower of the loan application associated with the document.
     * If the user is not a borrower, it denies the request.
     * It also checks if the document is verified or infected and denies the request if it is.
     *
     * @param User|Borrower $user The user to check.
     * @param Document $document The document to delete.
     * @return Response The response indicating if the deletion was allowed or denied.
     */
    public function deleteDocument($user, Document $document): Response
    {
        if (!$user instanceof Borrower) {
            Response::deny('You do not have permission to delete this document.');
        }

        if ($user->id !== $document->loanApplication->first()->borrower_id) {
            Response::deny('You do not have permission to delete this document.');
        }

        if ($document->isVerified()) {
            Response::deny('You cannot delete a verified document.');
        }

        if ($document->isInfected()) {
            Response::deny('You cannot delete an infected document.');
        }

        return Response::allow();
    }

    /**
     * Check if the staff user has permission to verify the document.
     *
     * Check if the user is an officer and if they are the assigned officer of the loan application associated with the document.
     * If the user is not an officer, check if the user has a role of 'loan_officer'.
     *
     * @param User $user The user to check.
     * @param Document $document The document to verify.
     * @return bool True if the user has permission to verify the document, false otherwise.
     */
    public function verifyDocument($user, Document $document): bool
    {
        if (!$user instanceof User) return false;

        if ($user->role === 'loan_officer' || ($user->role === 'officer' && $document->loanApplication->assigned_officer_id == $user->id)) return true;

        return false;
    }
}

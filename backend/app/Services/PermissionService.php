<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public function getPermissions(User $user): array
    {
        return [
            'view_dashboard' => true,
            'view_applications' => $user->canReviewApplications(),
            'review_applications' => $user->canReviewApplications(),
            'manage_loan_products' => $user->isModerator(),
            'manage_user' => $user->isAdmin(),
            'manage_borrowers' => in_array($user->role, ['admin', 'moderator']),
            'view_reports' => in_array($user->role, ['loan_officer', 'moderator']),
            'process_payments' => in_array($user->role, ['loan_officer', 'moderator']),
            'verify_documents' => $user->canVerifyDocuments(),
        ];
    }
}

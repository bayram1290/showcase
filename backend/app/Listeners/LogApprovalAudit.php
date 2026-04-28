<?php

namespace App\Listeners;

use App\Events\LoanApplicationApproved;
use App\Models\AuditLog;
use Carbon\Carbon;

class LogApprovalAudit
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the AuditLog create event about the approved loan application.
     */
    public function handle(LoanApplicationApproved $event): void
    {
        AuditLog::create([
            'action' => 'applicatin_approved',
            'user_id' => $event->approver->id,
            'loan_application_id' => $event->application->id,
            'old_data' => ['status' => 'under_review'],
            'new_data' => ['status' => 'approved', 'approved_at' => Carbon::now(), 'notes' => $event->remarks],
        ]);
    }
}

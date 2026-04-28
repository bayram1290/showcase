<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\LoanApplication;


class LogRejectionAudit
{
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(LoanApplication $event): void
    {
        AuditLog::create([
            'action' => 'application_rejected',
            'user_id' => $event->rejecter->id,
            'loan_application_id' => $event->application->id,
            'old_data' => json_encode($event->application->toArray()),
            'new_data' => json_encode(['status' => 'rejected', 'rejection_reason' => $event->remarks]),
        ]);
    }
}

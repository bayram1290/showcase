<?php

namespace App\Listeners;

use App\Events\LoanDisbursement as LoanDisbursementEvent;
use App\Models\AuditLog;

class LogDisbursementAudit
{

    /**
     * Handle the event.
     */
    public function handle(LoanDisbursementEvent $event): void
    {
        AuditLog::create([
            'action' => 'loan_disbursed',
            'new_data' => $event->loanAccount->toArray(),
            'user_id' => $event->disbursedByUserId,
            'loan_application_id' => $event->loanAccount->loanApplication->id,
        ]);
    }
}

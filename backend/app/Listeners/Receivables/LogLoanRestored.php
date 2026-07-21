<?php

namespace App\Listeners\Receivables;

use App\Domain\Receivables\Events\LoanRestoredEvent;
use App\Models\AuditLog;
use App\Models\LoanAccount;

use Carbon\Carbon;

class LogLoanRestored
{
    /**
     * Handle the LoanRestoredEvent by creating an AuditLog record.
     *
     * @param LoanRestoredEvent $event The event containing information about the loan restoration.
     * @return void
     */
    public function handle(LoanRestoredEvent $event): void
    {
        AuditLog::log(
            action: 'loan_restored',
            application_id: $event->loanAccount->loan_application_id,
            old_data: ['status' => $event->loanAccount->getOriginal('status')],
            new_data: [
                'status' => 'active',
                'restored_at' => Carbon::now(),
                'auditable_type' => LoanAccount::class,
                'auditable_id' => $event->loanAccount->id,
                'metadata' => ['reason' => $event->reason],
                'notes' => $event->reason ?? "Restored by {$event->restoredBy->getFullName()}",
            ]
        );
    }
}
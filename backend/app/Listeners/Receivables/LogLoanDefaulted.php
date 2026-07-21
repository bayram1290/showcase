<?php

namespace App\Listeners\Receivables;

use App\Domain\Receivables\Events\LoanDefaultedEvent;
use App\Models\AuditLog;
use App\Models\LoanAccount;

class LogLoanDefaulted
{
    /**
     * Handle the event of a loan being defaulted by creating an audit log record.
     *
     * @param LoanDefaultedEvent $event The event containing information about the loan default.
     * @return void
     */
    public function handle(LoanDefaultedEvent $event): void
    {
        AuditLog::log(
            action: 'loan_defaulted',
            application_id: $event->loanAccount->loan_application_id,
            old_data: ['status' => $event->loanAccount->getOriginal('status')],
            new_data: [
                'status' => 'defaulted',
                'defaulted_at' => now(),
                'auditable_type' => LoanAccount::class,
                'auditable_id' => $event->loanAccount->id,
                'metadata' => ['reason' => $event->reason],
                'notes' => $event->reason ?? "Defaulted by {$event->defaultedBy->getFullNameAttribute()}",
            ],
        );
    }
}
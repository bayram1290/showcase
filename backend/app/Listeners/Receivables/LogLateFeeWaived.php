<?php

namespace App\Listeners\Receivables;

use App\Domain\Receivables\Events\LateFeeWaivedEvent;
use App\Models\AuditLog;
use \App\Models\Installment;

class LogLateFeeWaived
{
    /**
     * Handle the LateFeeWaivedEvent by creating an AuditLog record
     *
     * @param LateFeeWaivedEvent $event The LateFeeWaivedEvent object containing the details of the waived late fee
     * @return void
    */
    public function handle(LateFeeWaivedEvent $event): void
    {
        AuditLog::log(
            action: 'late_fee_waived',
            application_id: $event->installment->loanAccount->loan_application_id,
            old_data: ['late_fee' => $event->installment->late_fee],
            new_data: [
                'late_fee' => 0,
                'auditable_type' => Installment::class,
                'auditable_id' => $event->installment->id,
                'metadata' => ['reason' => $event->reason],
                'notes' => $event->reason ?? "Waived by {$event->user->getFullNameAttribute()}",
            ],
        );
    }
}
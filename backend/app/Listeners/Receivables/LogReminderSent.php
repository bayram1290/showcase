<?php

namespace App\Listeners\Receivables;

use App\Domain\Receivables\Events\ReminderSentEvent;
use App\Models\AuditLog;
use App\Models\Installment;

use Carbon\Carbon;

class LogReminderSent
{
    /**
     * Log the event of a reminder being sent for an installment.
     *
     * @param ReminderSentEvent $event The event containing the reminder information.
     * @return void
     */
    public function handle(ReminderSentEvent $event): void
    {
        AuditLog::log(
            action: 'reminder_sent',
            application_id: $event->installment->loanAccount->loan_application_id,
            new_data: [
                'installment_id' => $event->installment->id,
                'auditable_type' => Installment::class,
                'auditable_id' => $event->installment->id,
                'metadata' => [
                    'attempt_number' => $event->attemptNumber,
                    'sent_by' => $event->sentBy->getFullNameAttribute(),
                    'sent_at' => Carbon::now(),
                ],
                'notes' => "Reminder sent for installment #{$event->installment->installment_number}"
            ]
        );
    }
}
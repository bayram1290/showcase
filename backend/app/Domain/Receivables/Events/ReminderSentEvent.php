<?php

namespace App\Domain\Receivables\Events;

use App\Models\Installment;
use App\Models\User;

class ReminderSentEvent extends DomainEvent
{
    /**
     * Constructor for the class.
     *
     * @param Installment $installment The installment object.
     * @param User $sentBy The user who sent the reminder.
     * @param int $attemptNumber The attempt number of the reminder.
     * @return void
     */
    public function __construct(
        public readonly Installment $installment,
        public readonly User $sentBy,
        public readonly int $attemptNumber,
    ) {
        parent::__construct();
    }

    /**
     * Get the name of the event: `reminder.sent`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'reminder.sent';
    }
}
<?php

namespace App\Domain\Receivables\Events;

use App\Models\Installment;
use App\Models\User;

class LateFeeWaivedEvent extends DomainEvent
{
    /**
     * Constructor for the class.
     *
     * @param Installment $installment The installment object.
     * @param User $user The user object.
     * @param string|null $reason The reason for waiving the late fee (optional).
     * @return void
     */
    public function __construct(
        public readonly Installment $installment,
        public readonly User $user,
        public readonly ?string $reason = null,
    ) {
        parent::__construct();
    }

    /**
     * Get the name of the event: `late_fee.waived`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'late_fee.waived';
    }
}
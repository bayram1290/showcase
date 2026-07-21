<?php

namespace App\Domain\Receivables\Events;

use App\Models\LoanAccount;
use App\Models\User;

class LoanDefaultedEvent extends DomainEvent
{
    /**
     * Initialize a new instance of the `DefaultedEvent` class with the specified parameters.
     *
     * @param LoanAccount $loanAccount The loan account that was defaulted.
     * @param User $defaultedBy The user who defaulted the loan account.
     * @param string|null $reason The reason for defaulting the loan account.
     * @return void
     */
    public function __construct(
        public readonly LoanAccount $loanAccount,
        public readonly User $defaultedBy,
        public readonly ?string $reason,
    ) {
        parent::__construct();
    }

    /**
     * Retrieve the name of the event: `loan.defaulted`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'loan.defaulted';
    }
}
<?php

namespace App\Domain\Receivables\Events;

use App\Models\LoanAccount;
use App\Models\User;

class LoanRestoredEvent extends DomainEvent
{
    /**
     * Initialize a new instance of the `LoanRestoredEvent` class with the specified parameters.
     *
     * @param LoanAccount $loanAccount The loan account that was restored.
     * @param User $restoredBy The user who restored the loan account.
     * @param string|null $reason The reason for restoring the loan account.
     * @return void
    */
    public function __construct(
        public readonly LoanAccount $loanAccount,
        public readonly User $restoredBy,
        public readonly ?string $reason,
    ) {
        parent::__construct();
    }

    /**
     * Retrieve the name of the event: `loan.restored`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'loan.restored';
    }
}
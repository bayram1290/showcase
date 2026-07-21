<?php

namespace App\Domain\Receivables\Events;

use App\Domain\Receivables\ValueObjects\NegotiationData;

class NegotiationCreatedEvent extends DomainEvent
{
    /**
     * Constructor for the class.
     *
     * @param int $loanAccountId The ID of the loan account.
     * @param NegotiationData $negotiationData The negotiation data.
     * @return void
     */
    public function __construct(
        public readonly int $loanAccountId,
        public readonly NegotiationData $negotiationData,
    ) {
        parent::__construct();
    }

    /**
     * Get the name of the event: `negotiation.created`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'negotiation.created';
    }
}
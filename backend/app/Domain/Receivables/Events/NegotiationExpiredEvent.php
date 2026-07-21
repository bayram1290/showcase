<?php

namespace App\Domain\Receivables\Events;

use App\Models\Negotiation;

class NegotiationExpiredEvent extends DomainEvent
{
    /**
     * Constructor for the class.
     *
     * @param Negotiation $negotiation The negotiation object.
     * @return void
     */
    public function __construct(public readonly Negotiation $negotiation)
    {
        parent::__construct();
    }

    /**
     * Get the name of the event: `negotiation.expired`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'negotiation.expired';
    }
}
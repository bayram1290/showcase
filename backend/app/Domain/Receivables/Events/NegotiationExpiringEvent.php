<?php

namespace App\Domain\Receivables\Events;

use App\Models\Negotiation;

class NegotiationExpiringEvent extends DomainEvent
{
    /**
     * Constructor for the class.
     *
     * @param Negotiation $negotiation The negotiation object.
     * @param int $daysRemaining The number of days remaining for the negotiation to expire.
     * @return void
     */
    public function __construct(public readonly Negotiation $negotiation, public readonly int $daysRemaining)
    {
        parent::__construct();
    }

    /**
     * Get the name of the event: `negotiation.expiring`.
     *
     * @return string The name of the event.
     */
    public function getEventName(): string
    {
        return 'negotiation.expiring';
    }
}
<?php

namespace App\Domain\Receivables\Enums;

enum NegotiationStatus: string
{
    case ACTIVE = 'active';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    /**
     * Check if the object is in the active state.
     *
     * @return bool Returns `true` if the object is in the active state, `false` otherwise.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if the object is in a terminal state.
     *
     * @return bool Returns `true` if the object is in a terminal state (accepted, rejected, expired, or cancelled), `false` otherwise.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CANCELLED]);
    }
}
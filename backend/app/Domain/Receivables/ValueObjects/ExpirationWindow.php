<?php

namespace App\Domain\Receivables\ValueObjects;

use App\Domain\Receivables\Exceptions\InvalidExpirationDateException;
use Carbon\Carbon;

final class ExpirationWindow
{
    /**
     * Construct a new ExpirationWindow instance
     *
     * @throws InvalidExpirationDateException if the expiration date is in the past
     * @throws InvalidExpirationDateException if the expiration date is too short
     * @throws InvalidExpirationDateException if the expiration date is too long
     * @return void
     */
    public function __construct(public readonly Carbon $expiresAt)
    {
        if ($this->expiresAt->isPast()) {
            throw InvalidExpirationDateException::past();
        }
        $days = now()->diffInDays($this->expiresAt, true);
        if ($days < 1) {
            throw InvalidExpirationDateException::tooShort();
        }
        if ($days > 365) {
            throw InvalidExpirationDateException::tooLong();
        }
    }

    /**
     * Create a new ExpirationWindow instance from a Carbon instance
     *
     * @param Carbon $expiresAt The expiration date
     * @return self The new ExpirationWindow instance
     */
    public static function from(Carbon $expiresAt): self
    {
        return new self($expiresAt);
    }

    /**
     * Check if the expiration window is active
     *
     * @return bool True if the expiration window is active, false otherwise
     */
    public function isActive(): bool
    {
        return $this->expiresAt->isFuture();
    }

    /**
     * Get the number of days remaining in the expiration window
     *
     * @return int The number of days remaining in the expiration window
     */
    public function getDaysRemaining(): int
    {
        return max(0, now()->diffInDays($this->expiresAt));
    }
}
<?php

namespace App\Domain\Receivables\ValueObjects;

use App\Domain\Receivables\Enums\NegotiationType;
use App\Http\Requests\Receivables\NegotiationRequest;
use Carbon\Carbon;

final class NegotiationData
{
    /**
     * Construct a new NegotiationData instance
     *
     * @param NegotiationNote $note The note of the negotiation
     * @param ExpirationWindow $expirationWindow The expiration window of the negotiation
     * @param NegotiationType|null $type The type of the negotiation
     * @param array|null $terms The terms of the negotiation
     * @param Money|null $acceptedAmount The accepted amount of the negotiation
     * @return void
    */
    public function __construct(
        public readonly NegotiationNote $note,
        public readonly ExpirationWindow $expirationWindow,
        public readonly ?NegotiationType $type,
        public readonly ?array $terms,
        public readonly ?Money $acceptedAmount,
    ) {}

    /**
     * Create a new Negotiation instance from a NegotiationRequest
     *
     * @param NegotiationRequest $request The NegotiationRequest
     * @return self The new Negotiation instance
     */
    public static function fromRequest(NegotiationRequest $request): self
    {
        $data = $request->validated();
        $type = isset($data['type']) ? NegotiationType::from($data['type']) : null;

        $expires_at = isset($data['expires_at'])
            ? Carbon::parse($data['expires_at'])
            : Carbon::now()->addDays($type?->getDefaultExpirationDays() ?? 30);

        $terms = $data['terms'] ?? null;
        if (is_string($terms)) {
            $terms = json_decode($terms, true);
        }

        return new self(
            note: NegotiationNote::from($data['note']),
            expirationWindow: ExpirationWindow::from($expires_at),
            type: $type,
            terms: $terms,
            acceptedAmount: isset($data['accepted_amount']) ? Money::fromDecimal((float) $data['accepted_amount']) : null,
        );
    }

    /**
     * Check if the negotiation is currently active
     *
     * @return bool True if the negotiation is active, false otherwise
     */
    public function isActive(): bool
    {
        return $this->expirationWindow->isActive();
    }

     /**
     * Get the number of days remaining until the expiration date
     *
     * @return int The number of days remaining
     */
    public function getDaysRemaining(): int
    {
        return $this->expirationWindow->getDaysRemaining();
    }

    /**
     * Check if the negotiation is expiring critically
     *
     * @param int $daysThreshold The number of days until the negotiation is considered critical Default is 3
     * @return bool True if the negotiation is expiring critically, false otherwise
     */
    public function isExpiringCritically(int $daysThreshold = 3): bool
    {
        return $this->isActive() && $this->getDaysRemaining() <= $daysThreshold;
    }

    /**
     * Convert the Negotiation instance to an array representation for audit logs
     *
     * @return array The array representation of the Negotiation instance for audit logs
     */
    public function toAuditLogData(): array
    {
        return [
            'note' => $this->note->value,
            'type' => $this->type?->value,
            'terms' => $this->terms,
            'accepted_amount' => $this->acceptedAmount?->toArray(),
            'expires_at' => $this->expirationWindow->expiresAt->toIso8601String(),
            'days_remaining' => $this->getDaysRemaining(),
            'is_active' => $this->isActive(),
            'is_critical' => $this->isExpiringCritically(),
        ];
    }
}
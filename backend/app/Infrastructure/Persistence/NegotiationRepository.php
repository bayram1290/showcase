<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Collection;

use App\Domain\Receivables\Contracts\NegotiationRepositoryInterface;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Models\LoanAccount;
use App\Models\Negotiation;

use Carbon\Carbon;

class NegotiationRepository implements NegotiationRepositoryInterface
{
    /**
     * Create a new negotiation for a loan account
     *
     * @param LoanAccount $loanAccount The loan account for the negotiation
     * @param NegotiationData $data The negotiation data
     * @return Negotiation The newly created negotiation
     */
    public function createForLoanAccount(LoanAccount $loanAccount, NegotiationData $data): Negotiation
    {
        return Negotiation::create([
            'loan_account_id' => $loanAccount->id,
            'type' => $data->type?->value,
            'note' => $data->note->value,
            'terms' => json_encode($data->terms),
            'accepted_amount' => $data->acceptedAmount?->toDecimal(),
            'expires_at' => $data->expirationWindow->expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * Retrieve the active negotiation for a loan account
     *
     * @param LoanAccount $loanAccount The loan account
     * @return Negotiation|null The active negotiation, or null if not found
     */
    public function getActiveByLoanAccount(LoanAccount $loanAccount): ?Negotiation
    {
        return Negotiation::where('loan_account_id', $loanAccount->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Mark a negotiation as expired
     *
     * @param Negotiation $negotiation The negotiation to mark as expired
     * @return void
     */
    public function markAsExpired(Negotiation $negotiation): void
    {
        $negotiation->update([
            'is_active' => false,
            'expired_at' => Carbon::now()
        ]);
    }

    /**
     * Retrieve a collection of negotiations that are expiring within a specified number of days
     *
     * @param int $daysThreshold The number of days threshold
     * @return Collection The collection of expiring negotiations
     */
    public function getExpiringNegotiations(int $daysThreshold): Collection
    {
        return Negotiation::where('is_active', true)
            ->where('expires_at', '<=', Carbon::now()->addDays($daysThreshold))
            ->get();
    }

    /**
     * Update the status of a negotiation
     *
     * @param Negotiation $negotiation The negotiation to update
     * @param string $status The new status
     * @return void
     */
    public function updateStatus(Negotiation $negotiation, string $status): void
    {
        $negotiation->update(['status' => $status]);
    }
}
<?php

namespace App\Domain\Receivables\Contracts;

use Illuminate\Support\Collection;

use App\Models\LoanAccount;
use App\Models\Negotiation;
use App\Domain\Receivables\ValueObjects\NegotiationData;

interface NegotiationRepositoryInterface
{
    /**
     * Create a new negotiation for a loan account with the specified data.
     *
     * @param LoanAccount $loanAccount The loan account to create the negotiation for.
     * @param NegotiationData $data The data for the negotiation.
     * @return Negotiation The newly created negotiation.
     */
    public function createForLoanAccount(LoanAccount $loanAccount, NegotiationData $data): Negotiation;

    /**
     * Get the active negotiation for a loan account.
     *
     * @param LoanAccount $loanAccount The loan account to get the active negotiation for.
     * @return Negotiation|null The active negotiation, or null if not found.
     */
    public function getActiveByLoanAccount(LoanAccount $loanAccount): ?Negotiation;

    /**
     * Mark a negotiation as expired.
     *
     * @param Negotiation $negotiation The negotiation to mark as expired.
     * @return void
     */
    public function markAsExpired(Negotiation $negotiation): void;

    /**
     * Get collection of negotiations that are expiring within the specified number of days.
     *
     * @param int $daysThreshold The number of days to check for expirations.
     * @return Collection A collection of expiring negotiations.
     */
    public function getExpiringNegotiations(int $daysThreshold): Collection;

    /**
     * Update the status of a negotiation.
     *
     * @param Negotiation $negotiation The negotiation to update.
     * @param string $status The new status for the negotiation.
     * @return void
     */
    public function updateStatus(Negotiation $negotiation, string $status): void;
}
<?php

namespace App\Domain\Receivables\Strategies;

use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\NegotiationData;

class NewScheduleStrategy implements NegotiationStrategy
{
    /**
     * Validate the negotiation data for a given loan account
     *
     * @param LoanAccount $loanAccount The loan account to validate
     * @param NegotiationData $data The negotiation data
     * @return void
     */
    public function validate(LoanAccount $loanAccount, NegotiationData $data): void {}

    /**
     * Apply the new schedule negotiation to a loan account
     *
     * @param LoanAccount $loanAccount The loan account to apply the new schedule to
     * @param NegotiationData $data The negotiation data.
     * @return array The metadata of the applied new schedule
     */
    public function apply(LoanAccount $loanAccount, NegotiationData $data): array
    {
        $loanAccount->update(['negotiation_data' => json_encode(['type' => 'new_schedule', 'applied_at' => now()])]);
        return ['schedule_updated' => true];
    }

    /**
     * Rollback the new schedule negotiation for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to rollback the new schedule for.
     * @param array $metadata The metadata of the applied new schedule
     * @return void
     */
    public function rollback(LoanAccount $loanAccount, array $metadata): void
    {
        $loanAccount->update(['negotiation_data' => null]);
    }

    /**
     * Get the type of the new schedule strategy: new_schedule
     *
     * @return string The type of the new schedule strategy
     */
    public function getType(): string
    {
        return 'new_schedule';
    }
}
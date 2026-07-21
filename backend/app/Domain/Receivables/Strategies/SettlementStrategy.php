<?php

namespace App\Domain\Receivables\Strategies;

use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Domain\Receivables\Exceptions\InvalidNegotiationException;

class SettlementStrategy implements NegotiationStrategy
{
    /**
     * Validate the negotiation data for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to validate
     * @param NegotiationData $data The negotiation data
     * @return void
     * @throws InvalidNegotiationException
     */
    public function validate(LoanAccount $loanAccount, NegotiationData $data): void
    {
        if (!in_array($loanAccount->status, ['active', 'paused', 'defaulted'])) {
            throw InvalidNegotiationException::invalidStatus($loanAccount->status);
        }
        if ($loanAccount->outstanding_balance <= 0) {
            throw InvalidNegotiationException::noOutstandingBalance();
        }
        if ($data->acceptedAmount === null || $data->acceptedAmount->toDecimal() <= 0) {
            throw InvalidNegotiationException::invalidAcceptedAmount(0);
        }
        if ($data->acceptedAmount->toDecimal() >= $loanAccount->outstanding_balance) {
            throw InvalidNegotiationException::invalidAcceptedAmount($data->acceptedAmount->toDecimal());
        }
    }

    /**
     * Apply the settlement negotiation to a loan account
     *
     * @param LoanAccount $loanAccount The loan account to apply the settlement to
     * @param NegotiationData $data The negotiation data
     * @return array The metadata of the applied settlement
     */
    public function apply(LoanAccount $loanAccount, NegotiationData $data): array
    {
        $accepted = $data->acceptedAmount->toDecimal();
        $write_off = $loanAccount->outstanding_balance - $accepted;
        $loanAccount->update([
            'outstanding_balance' => $accepted,
            'negotiation_data' => json_encode([
                'type' => 'settlement',
                'accepted_amount' => $accepted,
                'write_off' => $write_off,
                'applied_at' => now(),
            ]),
        ]);
        if ($accepted == 0) {
            $loanAccount->update([
                'status' => 'closed',
                'closed_at' => now()
            ]);
        }
        return ['write_off' => $write_off, 'loan_closed' => $accepted == 0];
    }

    /**
     * Rollback the settlement negotiation for a given loan account
     *
     * @param LoanAccount $loanAccount The loan account to rollback the settlement for
     * @param array $metadata The metadata of the applied settlement
     * @return void
     */
    public function rollback(LoanAccount $loanAccount, array $metadata): void
    {
        $loanAccount->outstanding_balance += $metadata['write_off'];
        $loanAccount->update(['negotiation_data' => null]);
        if ($loanAccount->status === 'closed') {
            $loanAccount->update([
                'status' => 'active',
                'closed_at' => null
            ]);
        }
    }

    /**
     * Get the type of the settlement strategy: settlement
     *
     * @return string The type of the settlement strategy
     */
    public function getType(): string
    {
        return 'settlement';
    }
}
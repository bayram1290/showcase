<?php

namespace App\Domain\Receivables\Strategies;

use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Domain\Receivables\Exceptions\InvalidNegotiationException;

class DiscountStrategy implements NegotiationStrategy
{
    /**
     * Validate the negotiation data for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to validate.
     * @param NegotiationData $data The negotiation data.
     * @return void
     * @throws InvalidNegotiationException
     */
    public function validate(LoanAccount $loanAccount, NegotiationData $data): void
    {
        if ($loanAccount->status !== 'active') {
            throw InvalidNegotiationException::invalidStatus($loanAccount->status);
        }
        if ($loanAccount->outstanding_balance <= 0) {
            throw InvalidNegotiationException::noOutstandingBalance();
        }
        if ($data->acceptedAmount === null || $data->acceptedAmount->toDecimal() >= $loanAccount->outstanding_balance) {
            throw InvalidNegotiationException::invalidAcceptedAmount($data->acceptedAmount?->toDecimal() ?? 0);
        }
    }

    /**
     * Apply the discount negotiation to a loan account.
     *
     * @param LoanAccount $loanAccount The loan account to apply the discount to.
     * @param NegotiationData $data The negotiation data.
     * @return array The metadata of the applied discount.
     */
    public function apply(LoanAccount $loanAccount, NegotiationData $data): array
    {
        $discount = $loanAccount->outstanding_balance - $data->acceptedAmount->toDecimal();
        $loanAccount->update([
            'outstanding_balance' => $data->acceptedAmount->toDecimal(),
            'negotiation_data' => json_encode([
                'type' => 'discount',
                'discount_applied' => $discount,
                'applied_at' => now(),
            ]),
        ]);
        return ['discount_applied' => $discount];
    }

    /**
     * Rollback the discount negotiation for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to rollback the discount for.
     * @param array $metadata The metadata of the applied discount.
     * @return void
     */
    public function rollback(LoanAccount $loanAccount, array $metadata): void
    {
        $loanAccount->outstanding_balance += $metadata['discount_applied'];
        $loanAccount->update(['negotiation_data' => null]);
    }

    /**
     * Get the type of the discount strategy.
     *
     * @return string The type of the discount strategy.
     */
    public function getType(): string
    {
        return 'discount';
    }
}
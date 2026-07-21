<?php

namespace App\Domain\Receivables\Strategies;

use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Domain\Receivables\Exceptions\InvalidNegotiationException;

class ExtensionStrategy implements NegotiationStrategy
{
    /**
     * Validate the negotiation data for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to validate.
     * @param NegotiationData $data The negotiation data
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
        if (!isset($data->terms['additional_months']) || $data->terms['additional_months'] <= 0) {
            throw new InvalidNegotiationException('Additional months must be provided.');
        }
    }

    /**
     * Apply the extension negotiation to a loan account.
     *
     * @param LoanAccount $loanAccount The loan account to apply the extension to
     * @param NegotiationData $data The negotiation data
     * @return array The metadata of the applied extension
     */
    public function apply(LoanAccount $loanAccount, NegotiationData $data): array
    {
        $fee = $data->type?->getApplicableFee($loanAccount)?->toDecimal() ?? 0;
        $loanAccount->outstanding_balance += $fee;
        $loanAccount->update([
            'negotiation_data' => json_encode([
                'type' => 'extension',
                'fee' => $fee,
                'additional_months' => $data->terms['additional_months'],
                'applied_at' => now(),
            ]),
        ]);
        return ['fee_added' => $fee];
    }

    /**
     * Rollback the extension negotiation for a given loan account.
     *
     * @param LoanAccount $loanAccount The loan account to rollback the extension for
     * @param array $metadata The metadata of the applied extension.
     * @return void
     */
    public function rollback(LoanAccount $loanAccount, array $metadata): void
    {
        $loanAccount->outstanding_balance -= $metadata['fee_added'];
        $loanAccount->update(['negotiation_data' => null]);
    }

    /**
     * Get the type of the extension strategy: `extension`
     *
     * @return string The type of the extension strategy.
     */
    public function getType(): string
    {
        return 'extension';
    }
}
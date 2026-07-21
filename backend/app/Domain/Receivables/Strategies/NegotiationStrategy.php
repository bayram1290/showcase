<?php

namespace App\Domain\Receivables\Strategies;

use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\NegotiationData;

interface NegotiationStrategy
{
    /**
     * Validate the negotiation data for a given loan account
     *
     * @param LoanAccount $loanAccount The loan account to validate
     * @param NegotiationData $data The negotiation data
     * @return void
     */
    public function validate(LoanAccount $loanAccount, NegotiationData $data): void;

    /**
     * Apply the negotiation to a loan account.
     *
     * @param LoanAccount $loanAccount The loan account to apply the negotiation to
     * @param NegotiationData $data The negotiation data
     * @return array The metadata of the applied negotiation
     */
    public function apply(LoanAccount $loanAccount, NegotiationData $data): array;

    /**
     * Rollback the negotiation for a given loan account
     *
     * @param LoanAccount $loanAccount The loan account to rollback the negotiation for
     * @param array $metadata The metadata of the applied negotiation
     * @return void
     */
    public function rollback(LoanAccount $loanAccount, array $metadata): void;

    /**
     * Get the type of the negotiation strategy
     *
     * @return string The type of the negotiation strategy
     */
    public function getType(): string;
}
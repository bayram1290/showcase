<?php

namespace App\Domain\Receivables\Exceptions;

class NegotiationAlreadyActiveException extends DomainException
{
    protected string $errorType = 'NEGOTIATION_ALREADY_ACTIVE';

    /**
     * Create an exception for when a negotiation is already active.
     *
     * @param int $loanAccountID The ID of the loan account.
     * @return self
     */
    public static function create(int $loanAccountID): self
    {
        $error = new self('There is already an active negotiation for this loan account.');
        $error->setContext(['loan_account_id' => $loanAccountID]);
        return $error;
    }
}
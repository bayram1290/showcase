<?php

namespace App\Domain\Receivables\Exceptions;

class InvalidNegotiationException extends DomainException
{
    protected string $errorType = 'INVALID_NEGOTIATION';

    /**
     * Create a new instance of the class with negotiation is not allowed for a loan with a specific status.
     *
     * @param string $status The status of the loan.
     * @return self A new instance of the class with the specified error message.
     */
    public static function invalidStatus(string $status): self
    {
        return new self("Negotiation is not allowed for loan with status: {$status}");
    }

    /**
     * Create a new instance of the class with the loan has no outstanding balance to negotiate.
     *
     * @return self A new instance of the class with the specified error message.
     */
    public static function noOutstandingBalance(): self
    {
        return new self('Loan has no outstanding balance to negotiate.');
    }

    /**
     * Create a new instance of the class with the accepted amount must be less than the outstanding balance.
     *
     * @param float $amount The accepted amount.
     * @return self A new instance of the class with the specified error message and context.
     */
    public static function invalidAcceptedAmount(float $amount): self
    {
        $e = new self('Accepted amount must be less than the outstanding balance.');
        $e->setContext(['accepted_amount' => $amount]);
        return $e;
    }
}
<?php

namespace App\Domain\Receivables\Exceptions;

class InvalidNegotiationNoteException extends DomainException
{
    protected string $errorType = 'INVALID_NEGOTIATION_NOTE';

    /**
     * Create an exception for a negotiation note that is too short.
     *
     * @return self
     */
    public static function tooShort(): self
    {
        return new self('Negotiation note must be at least 10 characters.');
    }

    /**
     * Create an exception for a negotiation note that is too long.
     *
     * @return self
     */
    public static function tooLong(): self
    {
        return new self('Negotiation note cannot exceed 1000 characters.');
    }

    /**
     * Create an exception for an empty negotiation note.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self('Negotiation note cannot be empty.');
    }
}
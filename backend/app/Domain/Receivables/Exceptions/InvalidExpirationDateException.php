<?php

namespace App\Domain\Receivables\Exceptions;

class InvalidExpirationDateException extends DomainException
{
    protected string $errorType = 'INVALID_EXPIRATION_DATE';

    /**
     * Create a new instance of the class with the expiration date cannot be in the past.
     *
     * @return self A new instance of the class with the specified error message.
     */
    public static function past(): self
    {
        return new self('Expiration date cannot be in the past.');
    }

    /**
     * Create a new instance of the class with the expiration must be at least 1 day from now.
     *
     * @return self A new instance of the class with the specified error message.
     */
    public static function tooShort(): self
    {
        return new self('Expiration must be at least 1 day from now.');
    }

    /**
     * Create a new instance of the class with the expiration cannot exceed 365 days.
     *
     * @return self A new instance of the class with the specified error message.
     */
    public static function tooLong(): self
    {
        return new self('Expiration cannot exceed 365 days.');
    }
}
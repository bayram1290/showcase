<?php

namespace App\Domain\Receivables\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected string $errorType = 'DOMAIN_ERROR';
    protected array $context = [];

    /**
     * Retrieve the error type.
     *
     * @return string The error type.
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Retrieve the context.
     *
     * @return array The context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the context.
     *
     * @param array $context The context to set.
     * @return void
     */
    protected function setContext(array $context): void
    {
        $this->context = $context;
    }
}
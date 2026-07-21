<?php

namespace App\Domain\Receivables\Exceptions;

class NoLateFeeException extends DomainException
{
    protected string $errorType = 'NO_LATE_FEE';

    /**
     * Create an exception for when an installment has no late fee to waive.
     *
     * @param int $installmentID The ID of the installment.
     * @return self
     */
    public static function create(int $installmentID): self
    {
        $error = new self('This installment has no late fee to waive.');
        $error->setContext(['installment_id' => $installmentID]);
        return $error;
    }
}
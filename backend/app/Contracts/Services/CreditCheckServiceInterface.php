<?php

namespace App\Contracts\Services;

use App\DataTransferObjects\CreditCheckData;
use App\Models\CreditCheck;

/**
 * Interface for credit check service.
 */
interface CreditCheckServiceInterface
{
    /**
     * Calculate the internal credit check score.
     *
     * @param CreditCheckData $data
     * @return array
     */
    public function calculateInternalScore(CreditCheckData $data): array;

    /**
     * Fetch the external credit check score.
     *
     * @param string $ssn
     * @return array
     */
    public function fetchExternalScore(string $ssn): array;

    /**
     * Retrieve the credit check for a loan application.
     *
     * @param int $loanApplicationId
     * @return CreditCheck if the credit check has been done, null otherwise
     */
    public function getForApplication(int $loanApplicationId): ?CreditCheck;
}

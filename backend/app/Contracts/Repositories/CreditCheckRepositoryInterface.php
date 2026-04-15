<?php

namespace App\Contracts\Repositories;

use App\Models\CreditCheck;

/**
 * Interface for the CreditCheck repository.
 */
interface CreditCheckRepositoryInterface
{
    /**
     * Create a new CreditCheck.
     *
     * @param array $data The data to create the CreditCheck with.
     * @return CreditCheck The created CreditCheck.
     */
    public function create(array $data): array;

    /**
     * Find the latest CreditCheck by loan application ID.
     *
     * @param int $loanApplicationId The loan application ID to find the CreditCheck for.
     * @return CreditCheck|null The found CreditCheck or null if not found.
     */
    public function findLatestByLoanApplicationId(int $loanApplicationId): ?CreditCheck;
}

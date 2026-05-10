<?php

namespace App\Contracts\Repositories;

use App\Models\LoanAccount;

interface LoanAccountRepositoryInterface
{
    /**
     * Create a new loan account with the given data.
     *
     * @param array $data The data to create the loan account.
     * @return LoanAccount The newly created loan account.
     */
    public function create(array $data): LoanAccount;

    /**
     * Find a loan account by its account number.
     *
     * @param string $accountNumber The account number to search for.
     * @return LoanAccount|null The loan account with the given account number, or null if not found.
     */
    public function findByAccountNumber(string $accountNumber): ?LoanAccount;

    /**
     * Update the status of a loan account.
     *
     * @param int $id The ID of the loan account to update.
     * @param string $status The new status of the loan account.
     * @param string|null $closedAt The date and time the loan account was closed (optional).
     * @return bool True if the status was successfully updated, false otherwise.
     */
    public function updateStatus(int $id, string $status, ?string $closedAt = null): bool;

    /**
     * Get the active loan account by loan application ID.
     *
     * @param int $loanApplicationID The ID of the loan application.
     * @return LoanAccount|null The active loan account, or null if not found.
     */
    public function getActiveByLoanApplication(int $loanApplicationID): ?LoanAccount;
}

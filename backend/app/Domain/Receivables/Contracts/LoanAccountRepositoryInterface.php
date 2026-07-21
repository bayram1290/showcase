<?php

namespace App\Domain\Receivables\Contracts;

use App\Models\LoanAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface LoanAccountRepositoryInterface
{
    /**
     * Retrieve a loan account by its ID.
     *
     * @param int $id The ID of the loan account.
     * @return LoanAccount|null The loan account with the specified ID, or null if not found.
     */
    public function findById(int $id): ?LoanAccount;

    /**
     * Retrieve a collection of loan accounts with overdue installments older than the specified cutoff date.
     *
     * @param Carbon $cutoffDate The cutoff date.
     * @return Collection The collection of loan accounts.
     */
    public function getLoansWithOldOverdue(Carbon $cutoffDate): Collection;

    /**
     * Retrieve a collection of loan accounts with active negotiations.
     *
     * @return Collection The collection of loan accounts.
     */
    public function getLoansWithActiveNegotiations(): Collection;

    /**
     * Update the status of a loan account.
     *
     * @param LoanAccount $loanAccount The loan account to update.
     * @param string $status The new status.
     * @param array $extraData Additional data to include in the update.
     * @return void
     */
    public function updateStatus(LoanAccount $loanAccount, string $status, array $extraData = []): void;
}
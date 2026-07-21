<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Collection;

use App\Domain\Receivables\Contracts\LoanAccountRepositoryInterface;
use App\Models\LoanAccount;

use Carbon\Carbon;

class ReceivablesLoanAccountRepository implements LoanAccountRepositoryInterface
{
    /**
     * Retrieve a loan account by its ID
     *
     * @param int $id The ID of the loan account
     * @return LoanAccount|null The loan account with the specified ID, or null if not found
     */
    public function findById(int $id): ?LoanAccount
    {
        return LoanAccount::find($id);
    }

    /**
     * Retrieve a collection of loan accounts that have overdue installments older than a specified cutoff date
     *
     * @param Carbon $cutoffDate The cutoff date
     * @return Collection The collection of loan accounts
     */
    public function getLoansWithOldOverdue(Carbon $cutoffDate): Collection
    {
        return LoanAccount::whereHas('installments', function ($q) use ($cutoffDate) {
            $q->where('status', 'overdue')
                ->where('due_date', '<', $cutoffDate);
        })->get();
    }

    /**
     * Retrieve a collection of loan accounts that have active negotiations
     *
     * @return Collection The collection of loan accounts
     */
    public function getLoansWithActiveNegotiations(): Collection
    {
        return LoanAccount::whereNotNull('negotiation_data')->get();
    }

    /**
     * Update the status of a loan account and saves the changes to the database
     *
     * @param LoanAccount $loanAccount The loan account to update
     * @param string $status The new status
     * @param array $extraData Optional extra data to include in the update
     * @return void
     */
    public function updateStatus(LoanAccount $loanAccount, string $status, array $extraData = []): void
    {
        $loanAccount->update(array_merge(['status' => $status], $extraData));
    }
}
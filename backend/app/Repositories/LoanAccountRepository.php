<?php

namespace App\Repositories;

use App\Contracts\Repositories\LoanAccountRepositoryInterface;
use App\Models\LoanAccount;
use Carbon\Carbon;

class LoanAccountRepository implements LoanAccountRepositoryInterface
{

    private const LOAN_APPLICATION_ACTIVE_STATUS = 'active';

    /**
     * Method creates a new loan account using the provided data and saves it to the database.
     *
     * @param array $data The data to create the loan account.
     * @return LoanAccount The newly created loan account.
     */
    public function create(array $data): LoanAccount
    {
        return LoanAccount::create($data);
    }

    /*
     * Method retrieves a loan account from the database based on its account number.
     *
     * @param string $accountNumber The account number to search for.
     * @return LoanAccount|null The loan account with the given account number, or null if not found.
     */
    public function findByAccountNumber(string $accountNumber): ?LoanAccount
    {
        return LoanAccount::where('account_number', $accountNumber)->first();
    }

    /**
     * Method updates the status of a loan account with the given ID.
     *
     * @param int $id The ID of the loan account to update.
     * @param string $status The new status of the loan account.
     * @param string|null $closedAt The date and time the loan account was closed (optional).
     * @return bool True if the status was successfully updated, false otherwise.
     */

    public function updateStatus(int $id, string $status, ?string $closedAt = null): bool
    {
        return LoanAccount::where('id', $id)
                ->update([
                    'status' => $status,
                    'closed_at' => $closedAt ?? Carbon::now()
                ]);
    }

    /**
     * This method retrieves the active loan account associated with the given loan application ID.
     *
     * @param int $loanApplicationID The ID of the loan application.
     * @return LoanAccount|null The active loan account, or null if not found.
     */
    public function getActiveByLoanApplication(int $loanApplicationID): ?LoanAccount
    {
        return LoanAccount::where('loan_application_id', $loanApplicationID)
                ->where('status', self::LOAN_APPLICATION_ACTIVE_STATUS)
                ->first();
    }

}
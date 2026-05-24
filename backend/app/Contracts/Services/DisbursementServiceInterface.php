<?php

namespace App\Contracts\Services;

use App\Models\LoanAccount;
use App\Exceptions\DisbursementException;
use App\DataTransferObjects\DisbursementData;

interface DisbursementServiceInterface
{
    /**
     * Disburse the specified loan amount to the borrower's account.
     *
     * @param DisbursementData $data The data required for the disbursement.
     *     - loan_account_id (int)
     *     - amount (float)
     *     - account_number (string)
     *     - routing_number (string)
     *     - borrower_id (int)
     *     - cashier_id (int)
     *
     * @return LoanAccount The updated loan account after the disbursement.
     */
    public function disburse(DisbursementData $data): LoanAccount;
}

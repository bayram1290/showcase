<?php

namespace App\Contracts\Services;

use App\Models\LoanAccount;
use App\Exceptions\DisbursementException;
use App\DataTransferObjects\DisbursementData;

interface DisbursementServiceInterface
{
    public function disburse(
        DisbursementData $data
    ): LoanAccount;
}

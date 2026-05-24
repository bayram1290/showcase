<?php

namespace App\Contracts\Services;

use App\DataTransferObjects\RepaymentData;
use App\Models\Installment;

interface RepaymentServiceInterface
{
    /**
     * Perform a repayment.
     *
     * @param RepaymentData $data The repayment data.
     * @return Installment The performed repayment.
     */
    public function performRepayment(RepaymentData $data): Installment;
}

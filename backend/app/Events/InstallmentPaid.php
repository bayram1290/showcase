<?php

namespace App\Events;

use App\Models\Installment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallmentPaid
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new InstallmentPaid event instance.
     *
     * @param Installment $installment The installment.
     * @param array $old_data The old data of the installment: paid_amount, paid_date, status, repayment_method_id.
     * @param int $userID The user ID.
     * @param string $repaymentExecutant The repayment executant.
     * @param float $amount The repayment amount.
     * @param int $installmentRepaymentMethodID The repayment method ID.
     * @param string|null $remarks The remarks.
     */
    public function __construct(
        public Installment $installment,
        public array $old_data,
        public int $userID,
        public string $repaymentExecutant,
        public float $amount,
        public int $installmentRepaymentMethodID,
        public ?string $remarks,
    ) {}
}

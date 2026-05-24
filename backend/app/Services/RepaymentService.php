<?php

namespace App\Services;

use Override;

use Illuminate\Support\Facades\DB;

use App\Contracts\Services\RepaymentServiceInterface;
use App\DataTransferObjects\RepaymentData;
use App\Models\Installment;
use App\Events\InstallmentPaid as InstallmentPaidEvent;

class RepaymentService implements RepaymentServiceInterface
{
    /**
     * Perform a repayment for an installment.
     *
     * @param RepaymentData $data The repayment data.
     * @return Installment The performed repayment.
     * @throws \Exception If the installment has already been fully paid off.
     */
    #[Override]
    public function performRepayment(RepaymentData $data): Installment
    {
        $installment = Installment::where('installment_uuid', $data->repaymentUUID)?->first();
        $old_installment_data_for_event = $installment?->only(['status', 'paid_amount', 'paid_date', 'repayment_method_id']);

        $amount_to_pay = $installment->due_amount - $installment->paid_amount;

        if ($amount_to_pay <= 0) {
            throw new \Exception('This installment has already been fully paid off.');
        }

        return DB::transaction(function () use ($installment, $amount_to_pay, $data, $old_installment_data_for_event) {
            $installment->markAsPaid($data->amount, $data->installmentRepaymentMethodID);
            event(new InstallmentPaidEvent($installment, $old_installment_data_for_event, $data->userID, $data->repaymentExecutant, $data->installmentRepaymentMethodID, $amount_to_pay, $data->remarks));

            return $installment->fresh();
        });
    }
}
<?php

namespace App\Listeners;

use App\Events\InstallmentPaid;
use App\Jobs\SendPaymentReceiptJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentReceipt implements ShouldQueue
{
    /**
     * Handle the event when an installment is paid.
     *
     * @param InstallmentPaid $event The event containing information about the paid installment.
     * @return void
     */
    public function handle(InstallmentPaid $event): void
    {
        SendPaymentReceiptJob::dispatch($event->installment, $event->installmentRepaymentMethodID);
    }
}

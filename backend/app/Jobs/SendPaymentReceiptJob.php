<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

use App\Models\Installment;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Log;

class SendPaymentReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new SendPaymentReceiptJob instance.
     *
     * @param Installment $installment The installment.
     * @param int $installmentRepaymentMethodID The repayment method ID.
     */
    public function __construct(
        protected Installment $installment,
        protected int $installmentRepaymentMethodID
    ) {}

    /**
     * Handle the job of sending a payment receipt email to the borrower using the provided installment and repayment method ID.
     *
     * @return void
     */
    public function handle(): void
    {
        $borrower = $this->installment->loanAccount->loanApplication->borrower;
        Mail::to(
            $borrower->email
        )->send(
            new PaymentReceiptMail($this->installment, $this->installmentRepaymentMethodID)
        );

        Log::info('Payment receipt sent to ' . $borrower->email);
    }
}

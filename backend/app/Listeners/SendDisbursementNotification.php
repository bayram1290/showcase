<?php

namespace App\Listeners;

use App\Events\LoanDisbursement as LoanDisbursementEvent;
use App\Jobs\SendDisbursementEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDisbursementNotification implements ShouldQueue
{

    /**
     * Handle the sending notification of disbursement event.
     */
    public function handle(LoanDisbursementEvent $event): void
    {
        SendDisbursementEmailJob::dispatch($event->loanAccount);
    }
}

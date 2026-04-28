<?php

namespace App\Listeners;

use App\Events\LoanApplicationRejected;
use App\Jobs\SendRejectionEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRejectionNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(LoanApplicationRejected $event): void
    {
        SendRejectionEmailJob::dispatch($event->application, $event->rejecter);
    }
}

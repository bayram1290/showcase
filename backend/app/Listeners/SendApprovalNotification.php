<?php

namespace App\Listeners;

use App\Events\LoanApplicationApproved;
use App\Jobs\SendApprovalEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendApprovalNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    public function handle(LoanApplicationApproved $event): void
    {
        SendApprovalEmailJob::dispatch($event->application, $event->approver);
    }
}

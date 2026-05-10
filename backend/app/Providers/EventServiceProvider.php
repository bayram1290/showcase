<?php

namespace App\Providers;

use App\Events\LoanApplicationApproved;
use App\Events\LoanApplicationRejected;
use App\Events\LoanDisbursement;

use App\Listeners\LogApprovalAudit;
use App\Listeners\LogRejectionAudit;
use App\Listeners\SendApprovalNotification;
use App\Listeners\SendRejectionNotification;
use App\Listeners\SendDisbursementNotification;
use App\Listeners\LogDisbursementAudit;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        LoanApplicationApproved::class => [
            SendApprovalNotification::class,
            LogApprovalAudit::class
        ],
        LoanApplicationRejected::class => [
            SendRejectionNotification::class,
            LogRejectionAudit::class
        ],
        LoanDisbursement::class => [
            SendDisbursementNotification::class,
            LogDisbursementAudit::class
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

}
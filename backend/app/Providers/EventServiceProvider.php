<?php

namespace App\Providers;

use App\Events\LoanApplicationApproved;
use App\Listeners\SendApprovalNotification;
use App\Listeners\LogApprovalAudit;

use App\Events\LoanApplicationRejected;
use App\Listeners\SendRejectionNotification;
use App\Listeners\LogRejectionAudit;

use App\Events\LoanDisbursement;
use App\Listeners\SendDisbursementNotification;
use App\Listeners\LogDisbursementAudit;

use App\Events\InstallmentPaid;
use App\Listeners\SendPaymentReceipt;
use App\Listeners\LogPaymentAudit;

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
        InstallmentPaid::class => [
            SendPaymentReceipt::class,
            LogPaymentAudit::class
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

}
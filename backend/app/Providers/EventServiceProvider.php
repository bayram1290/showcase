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

use App\Domain\Receivables\Events\LateFeeWaivedEvent;
use App\Listeners\Receivables\LogLateFeeWaived;
use App\Domain\Receivables\Events\LoanDefaultedEvent;
use App\Listeners\Receivables\LogLoanDefaulted;
use App\Domain\Receivables\Events\LoanRestoredEvent;
use App\Listeners\Receivables\LogLoanRestored;
use App\Domain\Receivables\Events\ReminderSentEvent;
use App\Listeners\Receivables\LogReminderSent;

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
        LateFeeWaivedEvent::class => [LogLateFeeWaived::class],
        LoanDefaultedEvent::class => [LogLoanDefaulted::class],
        LoanRestoredEvent::class => [LogLoanRestored::class],
        ReminderSentEvent::class => [LogReminderSent::class],
    ];

    public function boot(): void
    {
        parent::boot();
    }

}
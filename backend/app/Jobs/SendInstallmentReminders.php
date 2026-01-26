<?php

namespace App\Jobs;

use App\Models\Installment;
use App\Notifications\InstallmentDueReminder;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInstallmentReminders implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable {
        Dispatchable::dispatch insteadof Queueable;
        Queueable::dispatch as queueableDispatch;
    }
    use SerializesModels;

    private const DAYS_UNTIL_DUE = 3;

    public function __construct()
    {}

    public function handle(): void
    {
        $installments = Installment::with(['loanAccount.loanApplication.user'])
            ->where('status','pending')
            ->where('due_date', now()->addDays(self::DAYS_UNTIL_DUE))
            ->get();

        foreach ($installments as $installment) {
            try {
                $user = $installment->loanAccount->$user;
                $user->notify(
                    new InstallmentDueReminder($installment, self::DAYS_UNTIL_DUE)
                );
            } catch (\Exception $e) {
                Log::error("Failed to send 3-day reminder for installment {$installment->id}: {$e->getMessage()}");
            }
        }

        $tommorrow = now()->addDay()->toDateString();

        $tomarrow_installments = Installment::with(['loanAccount.loanApplication.user'])
            ->where('status','pending')
            ->where('due_date', $tommorrow)
            ->whereHas('loanAccount.loanApplication.user')
            ->get();

        foreach ($tomarrow_installments as $tomorrow_installment) {
            try {
                $user = $installment->loanAccount->loanApplication->user;
                $user->notify(
                    new InstallmentDueReminder($tomorrow_installment, 1)
                );
            } catch (\Exception $e) {
                Log::error("Failed to send tomorrow reminder for installment {$installment->id}: {$e->getMessage()}");
            }
        }

        $today = now()->toDateString();
        $today_installments = Installment::with(['loanAccount.loanApplication.user'])
            ->where('status', 'pending')
            ->whereDate('due_date', $today)
            ->whereHas('loanAccount.loanApplication.user')
            ->get();

        foreach ($today_installments as $today_installment) {
            try {
                $user = $installment->loanAccount->loanApplication->user;
                $user->notify(
                    new InstallmentDueReminder($today_installment, 0)
                );
            } catch (\Exception $e) {
                Log::error("Failed to send today reminder for installment {$installment->id}: {$e->getMessage()}");
            }
        }


        $overdue_installments = Installment::with(["loanAccount.loanApplication.user"])
            ->where("status","pending")
            ->where('late_fee', 0)
            ->get();

        foreach ($overdue_installments as $overdue_installment) {
            try {
                $loan_product = $overdue_installment->loanAccount->loanApplication->loanProduct;
                if ($loan_product && $loan_product->late_fee > 0) {
                    $overdue_installment->addLateFee($loan_product->late_fee);
                }
            } catch (\Exception $e) {
                Log::error("Failed to addd late fee for installment {$overdue_installment->id}: {$e->getMessage()}");
            }
        }
    }
}

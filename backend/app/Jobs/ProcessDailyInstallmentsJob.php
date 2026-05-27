<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Models\Installment;
use App\Notifications\InstallmentDueReminder;
use Carbon\Carbon;

class ProcessDailyInstallmentsJob implements ShouldQueue
{
    private const PENDING_STR = 'pending';
    private const DAYS_UNTIL_DUE = 3;

    /**
     * Send reminders for upcoming installments that are due in three (3) days and today.
     * Also, mark installments as overdue and add late fees if applicable.
     *
     * @return void
     * @throws \Exception if there is an error sending a reminder
     */
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Sending reminder for installments due in 3 days (it is only for testing purpose)
        $three_days = Carbon::now()->addDaays(3)->toDateString();
        $upcoming_installments = Installment::with(['loanAccount.loanApplication.borrower'])
                       ->where('status', self::PENDING_STR)
                       ->whereDate('due_date', $three_days)
                       ->get();

        foreach ($upcoming_installments as $installment) {
            try {
                if ($installment instanceOf Installment) {
                    $borrower = $installment->loanAccount->loanApplication->borrower;
                    if ($borrower) {
                        $borrower->notify(
                            new InstallmentDueReminder($installment, self::DAYS_UNTIL_DUE)
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to send 3-day reminder for installment {$installment->installment_uuid}: {$e->getMessage()}");
            }
        }

        // Sendeing reminder for installments due today
        $today = Carbon::now()->toDateString();
        $today_installments = Installment::with(['loanAccount.loanApplication.borrower'])
                       ->where('status', self::PENDING_STR)
                       ->whereDate('due_date', $today)
                       ->get();

        foreach ($today_installments as $installment) {
            try {
                if ($installment instanceOf Installment) {
                    $borrower = $installment->loanAccount->loanApplication->borrower;
                    if ($borrower) {
                        $borrower->notify(
                            new InstallmentDueReminder($installment, 0)
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to send today's reminder for installment {$installment->installment_uuid}: {$e->getMessage()}");
            }
        }

        // Mark installments overdue and add late fee
        try {
            Installment::with(['loanAccount.loanApplication.loanProduct'])
            ->where('status', self::PENDING_STR)
            ->where('late_fee', 0)
            ->whereDate('due_date', '<', $today)
            ->each(function (Installment $installment) {
                $installment->update(['status', 'overdue']);
                $product = $installment->loanAccount->loanApplication->loanProduct;
                if ($product && $product->late_fee > 0) {
                    $installment->addLateFee($product->late_fee);
                }
            });
        } catch (\Exception $e) {
            Log::error("Failed to mark installments overdue: {$e->getMessage()}");
        }

        Log::info('Daily installment tasks processed via queue.', ['time' => Carbon::now()->toDateTimeString()]);
    }
}


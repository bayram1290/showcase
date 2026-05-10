<?php

namespace App\Jobs;

use App\Mail\LoanDisbursedMail;
use App\Models\LoanAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendDisbursementEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected LoanAccount $loanAccount
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $borrower = $this->loanAccount->loanApplication->borrower;
        Mail::to(
            $borrower->email
        )->send(new LoanDisbursedMail($this->loanAccount));
    }
}

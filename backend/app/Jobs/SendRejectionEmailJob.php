<?php

namespace App\Jobs;

use App\Mail\LoanRejectedMail;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRejectionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        protected LoanApplication $application,
        protected User $rejecter
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to(
            $this->application->borrower->email
        )->send(new LoanRejectedMail($this->application, $this->rejecter));
    }
}

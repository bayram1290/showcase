<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

use App\Models\LoanApplication;
use App\Models\User;
use App\Mail\LoanApprovedMaiL;

class SendApprovalEmailJob implements ShouldQueue
{
    use SerializesModels, InteractsWithQueue, Dispatchable;

    public function __construct(protected LoanApplication $application, protected User $approver) {}

    public function handle(): void
    {
        $borrower = $this->application->borrower;
        Mail::to($borrower->email)->send(new LoanApprovedMaiL($this->application, $this->approver));
    }
}

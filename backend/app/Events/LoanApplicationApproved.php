<?php

namespace App\Events;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicationApproved
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    /**
     * Create a new Loan application approval event instance.
     *
     * @param LoanApplication $application The loan application that was approved.
     * @param User $approver The user who approved the loan application.
     * @param string|null $remarks The remarks for the approval.
     */
    public function __construct(
        public LoanApplication $application,
        public User $approver,
        public ?string $remarks = null
    ) {}
}

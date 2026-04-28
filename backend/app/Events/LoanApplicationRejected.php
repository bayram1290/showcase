<?php

namespace App\Events;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicationRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new Loan application rejection event instance.
     *
     * @param LoanApplication $application The loan application that was rejected.
     * @param User $rejecter The user who rejected the loan application.
     * @param string|null $remarks The remarks for the rejection.
     */
    public function __construct(
        public LoanApplication $application,
        public User $rejecter,
        public ?string $remarks = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    /*
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
    */
}

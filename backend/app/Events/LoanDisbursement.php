<?php

namespace App\Events;

use App\Models\LoanAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanDisbursement
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new disbursement event instance.
     */
    public function __construct(
        public LoanAccount $loanAccount,
        public int $disbursedByUserId,
        public ?string $remarks,
    ) {}

}

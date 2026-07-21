<?php

namespace App\Listeners\Receivables;

use App\Domain\Receivables\Events\NegotiationCreatedEvent;
use App\Models\AuditLog;
use App\Models\LoanAccount;

class LogNegotiationCreated
{

    /**
     * Handle the NegotiationCreatedEvent by creating an audit log entry.
     *
     * @param NegotiationCreatedEvent $event The event containing the negotiation data.
     * @return void
     */
    public function handle(NegotiationCreatedEvent $event): void
    {
        $loan_account = LoanAccount::find($event->loanAccountId) ?? null;
        $loan_application_id = $loan_account ? $loan_account->loan_application_id : null;

        AuditLog::log(
            action: 'negotiation_created',
            application_id: $loan_application_id,
            new_data: $event->negotiationData->toAuditLogData(),
        );
    }
}
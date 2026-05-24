<?php

namespace App\Listeners;

use App\Events\InstallmentPaid;
use App\Models\AuditLog;
use App\Models\InstallmentRepaymentMethod;

class LogPaymentAudit
{
    /**
     * Handle the InstallmentPaid event via logging the payment.
     *
     * @param InstallmentPaid $event The InstallmentPaid event.
     * @return void
     */
    public function handle(InstallmentPaid $event): void
    {
        AuditLog::create([
            'action' => 'installment_paid',
            'user_id' => $event->userID,
            'loan_application_id' => $event->installment->loanAccount->loan_application_id,
            'old_data' => $event->old_data,
            'new_data' => [
                'status' => $event->installment->status,
                'paid_amount' => $event->installment->paid_amount,
                'paid_date' => $event->installment->paid_date,
                'payment_executant' => $event->repaymentExecutant,
                'outstanding_balance' => $event->installment->loanAccount->outstanding_balance,
                'notes' => $event->remarks,
                'installment_repayment_method' => InstallmentRepaymentMethod::find($event->installmentRepaymentMethodID)?->name ?? "Installment Repayment Method not found with ID: {$event->installmentRepaymentMethodID}",
            ]
        ]);
    }
}

<?php

namespace App\Domain\Receivables\Contracts;

use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Domain\Receivables\ValueObjects\OverdueFilterData;
use App\Domain\Receivables\ValueObjects\ReceivablesSummaryDTO;
use App\Domain\Receivables\ValueObjects\AgingReportDTO;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReceivablesServiceInterface
{
    /**
     * Retrieve a paginated list of overdue installments based on the specified filter.
     *
     * @param OverdueFilterData $filter The filter data.
     * @return LengthAwarePaginator The paginated list of overdue installments.
     */
    public function getOverdueInstallments(OverdueFilterData $filter): LengthAwarePaginator;

    /** Initiate a negotiation for a loan account with the specified data.
     *
     * @param LoanAccount $loanAccount The loan account to negotiate for.
     * @param NegotiationData $data The data for the negotiation.
     * @param User $user The user initiating the negotiation.
     * @return void
     */
    public function negotiate(LoanAccount $loanAccount, NegotiationData $data, User $user): void;

    /**
     * Appliy a late fee to an overdue installment.
     *
     * @param Installment $installment The overdue installment.
     * @param User $user The user applying the late fee.
     * @param string|null $reason The reason for applying the late fee.
     * @return void
     */
    public function waiveLateFee(Installment $installment, User $user, ?string $reason = null): void;

    /**
     * Send a reminder for an overdue installment to the borrower.
     *
     * @param Installment $installment The overdue installment.
     * @param User $user The user to send the reminder to.
     * @return void
     */
    public function sendReminder(Installment $installment, User $user): void;

    /**
     * Mark a loan account as defaulted.
     *
     * @param LoanAccount $loanAccount The loan account to mark as default.
     * @param User $user The user marking the loan account as default.
     * @param string|null $reason The reason for marking the loan account as default.
     * @return void
     */
    public function markDefault(LoanAccount $loanAccount, User $user, ?string $reason = null): void;

    /**
     * Restore a loan account from defaulted.
     *
     * @param LoanAccount $loanAccount The loan account to restore.
     * @param User $user The user restoring the loan account.
     * @param string|null $reason The reason for restoring the loan account.
     * @return void
     */
    public function restore(LoanAccount $loanAccount, User $user, ?string $reason = null): void;

    /**
     * Retrieve the summary of receivables.
     *
     * @return ReceivablesSummaryDTO The summary of receivables.
     */
    public function getReceivablesSummary(): ReceivablesSummaryDTO;

    /**
     * Retrieve the Aging Report.
     *
     * @return AgingReportDTO The aging report.
     */
    public function getAgingReport(): AgingReportDTO;
}
<?php

namespace App\Application\Receivables;

use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Domain\Receivables\Contracts\NegotiationRepositoryInterface;
use App\Domain\Receivables\Contracts\LoanAccountRepositoryInterface;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Domain\Receivables\ValueObjects\OverdueFilterData;
use App\Domain\Receivables\ValueObjects\OverdueInstallmentDTO;
use App\Domain\Receivables\ValueObjects\ReceivablesSummaryDTO;
use App\Domain\Receivables\ValueObjects\AgingReportDTO;
use App\Domain\Receivables\ValueObjects\AgingBucketDTO;
use App\Domain\Receivables\Events\NegotiationCreatedEvent;
use App\Domain\Receivables\Events\LoanDefaultedEvent;
use App\Domain\Receivables\Events\LoanRestoredEvent;
use App\Domain\Receivables\Events\ReminderSentEvent;
use App\Domain\Receivables\Exceptions\NegotiationAlreadyActiveException;
use App\Domain\Receivables\Exceptions\NoLateFeeException;
use App\Domain\Receivables\Events\LateFeeWaivedEvent;
use App\Exceptions\ReceivablesException;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\User;
use App\Models\AuditLog;
use App\Notifications\CollectionReminder;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReceivablesService implements ReceivablesServiceInterface
{
    private const DEFAULT_PAGINATION = 15;
    private const LATE_FEE_WAIVE_MAX_DAYS = 30;
    private const REMINDER_COOLDOWN_DAYS = 3;
    private const DEFAULT_OVERDUE_DAYS = 90;

    public function __construct(
        private readonly NegotiationRepositoryInterface $negotiationRepository,
        private readonly LoanAccountRepositoryInterface $loanAccountRepository,
        private readonly Dispatcher $eventDispatcher,
    ) {}

    /**
     * Retrieve overdue installments based on the provided filter.
     *
     * @param OverdueFilterData $filter The filter data for retrieving overdue installments.
     * @return LengthAwarePaginator The paginated list of overdue installments.
     */
    public function getOverdueInstallments(OverdueFilterData $filter): LengthAwarePaginator
    {

        $query = Installment::with([
            'loanAccount.loanApplication.borrower',
            'loanAccount.loanApplication.loanProduct',
        ])->where('status', 'overdue');

        if ($filter->usesDateFilter()) {
            $query->where('due_date', '<=', $filter->cutoffDate);
        }
        if ($filter->loanAccountID) {
            $query->where('loan_account_id', $filter->loanAccountID);
        }
        if ($filter->borrowerID) {
            $query->whereHas('loanAccount.loanApplication', fn($q) => $q->where('borrower_id', $filter->borrowerID));
        }

        $paginator = $query->orderBy('due_date', 'asc')
            ->paginate($filter->perPage ?? config('helper.default_pagination_length', self::DEFAULT_PAGINATION));

        $items = OverdueInstallmentDTO::collectionFromCollection($paginator->getCollection());

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $paginator->path()]
        );
    }

    /**
     * Negotiate a loan account based on the provided data.
     *
     * @param LoanAccount $loanAccount The loan account to negotiate.
     * @param NegotiationData $data The negotiation data.
     * @param User $user The user initiating the negotiation.
     * @throws NegotiationAlreadyActiveException If a negotiation is already active for the loan account.
     * @return void
     */
    public function negotiate(LoanAccount $loanAccount, NegotiationData $data, User $user): void
    {
        if ($this->negotiationRepository->getActiveByLoanAccount($loanAccount) !== null) {
            throw NegotiationAlreadyActiveException::create($loanAccount->id);
        }

        $strategy = $data->type?->getStrategy();
        if ($strategy) {
            $strategy->validate($loanAccount, $data);
        }

        DB::transaction(function() use ($loanAccount, $data) {
            $this->negotiationRepository->createForLoanAccount($loanAccount, $data);
            $this->eventDispatcher->dispatch(new NegotiationCreatedEvent($loanAccount->id, $data));
        });
    }

    /**
     * Waive the late fee for an installment.
     *
     * @param Installment $installment The installment to waive the late fee for.
     * @param User $user The user waiving the late fee.
     * @param ?string $reason The reason for waiving the late fee.
     * @throws NoLateFeeException If the installment does not have a late fee.
     * @throws ReceivablesException If the late fee cannot be waived after the maximum days.
     * @return void
     */
    public function waiveLateFee(Installment $installment, User $user, ?string $reason = null): void
    {
        if ($installment->status !== 'overdue') {
            throw NoLateFeeException::create($installment->id);
        }

        if ($installment->late_fee == 0) {
            throw NoLateFeeException::create($installment->id);
        }

        $late_fee_waiver_max_days = config('receivables.late_fee_waiver_max_days', self::LATE_FEE_WAIVE_MAX_DAYS);
        if ((int) floor(Carbon::parse($installment->due_date)->diffInDays(Carbon::now())) > $late_fee_waiver_max_days) {
            throw ReceivablesException::lateFeeWaiverFailed('Late fee cannot be waived after '. $late_fee_waiver_max_days .' days.');
        }

        DB::transaction(function () use ($installment, $user, $reason) {
            $installment->update(['late_fee' => 0]);
            $this->eventDispatcher->dispatch(new LateFeeWaivedEvent($installment, $user, $reason));
        });
    }

    /**
     * Send a reminder for an overdue installment to the borrower.
     *
     * @param Installment $installment The overdue installment.
     * @param User $user The user to send the reminder to.
     * @throws ReceivablesException If the borrower has no contact information or a reminder has been sent recently.
     * @return void
     */
    public function sendReminder(Installment $installment, User $user): void
    {
        $borrower = $installment->loanAccount->loanApplication->borrower;
        if (!$borrower || (!$borrower->email && !$borrower->phone)) {
            throw ReceivablesException::reminderSendFailed('Borrower has no contact information.');
        }

        $last_reminder = AuditLog::where('loan_application_id', $installment->loanAccount->loan_application_id)
            ->where('action', 'reminder_sent')
            ->latest()
            ->first();


        $reminder_cooldown_days = config('receivables.reminder_cooldown_days', self::REMINDER_COOLDOWN_DAYS);
        if ($last_reminder && $last_reminder->created_at->addDays($reminder_cooldown_days)->isFuture()) {
            throw ReceivablesException::reminderSendFailed('Reminder already sent recently.');
        }

        DB::transaction(function() use ($installment, $user, $borrower) {
            $borrower->notify(new CollectionReminder($installment));
            $this->eventDispatcher->dispatch(new ReminderSentEvent($installment, $user, 1));
        });
    }

    /**
     * Mark a loan account as defaulted.
     *
     * @param LoanAccount $loanAccount The loan account to mark as defaulted.
     * @param User $user The user performing the action.
     * @param string|null $reason The reason for marking the loan account as defaulted.
     * @throws ReceivablesException If the loan account has no overdue installments older than 90 days.
     * @return void
     */
    public function markDefault(LoanAccount $loanAccount, User $user, ?string $reason = null): void
    {
        $cutoff = Carbon::now()->subDays(config('receivables.default_overdue_days', self::DEFAULT_OVERDUE_DAYS));
        $has_old_oerdue = $this->loanAccountRepository->getLoansWithOldOverdue($cutoff)->contains($loanAccount);
        if (!$has_old_oerdue) {
            throw ReceivablesException::defaultationFailed('No overdue installments older than 90 days.');
        }

        DB::transaction(function() use ($loanAccount, $user, $reason) {
            $loanAccount->update([
                'status' => 'defaulted',
                'defaulted_at' => Carbon::now()
            ]);
            $this->eventDispatcher->dispatch(new LoanDefaultedEvent($loanAccount, $user, $reason));
        });
    }

    /**
     * Restore a defaulted loan account.
     *
     * @param LoanAccount $loanAccount The loan account to restore.
     * @param User $user The user performing the action.
     * @param string|null $reason The reason for restoring the loan account.
     * @throws ReceivablesException If the loan account's status is not 'defaulted'.
     * @return void
     */
    public function restore(LoanAccount $loanAccount, User $user, ?string $reason = null): void
    {
        if ($loanAccount->status !== 'defaulted') {
            throw ReceivablesException::restorationFailed('Only defaulted loans can be restored.');
        }

        DB::transaction(function () use ($loanAccount, $user, $reason) {
            $loanAccount->update([
                'status' => 'active',
                'restored_at' => Carbon::now()
            ]);
            $this->eventDispatcher->dispatch(new LoanRestoredEvent($loanAccount, $user, $reason));
        });
    }

    /**
     * Retrieve the receivables summary.
     *
     * @return ReceivablesSummaryDTO The receivables summary.
     */
    public function getReceivablesSummary(): ReceivablesSummaryDTO
    {
        $totals = Installment::where('status', 'overdue')
            ->selectRaw('COUNT(*) as total_count, SUM(late_fee) as total_late_fees, SUM(due_amount) as total_overdue_amount')
            ->first();

        $defaulted_count = LoanAccount::where('status', 'defaulted')->count();
        $active_loans = LoanAccount::where('status', 'active')->count();
        $total_portfolio = LoanAccount::sum('disbursed_amount');

        return ReceivablesSummaryDTO::fromAggregates(
            totalOverdueInstallments: (int) ($totals->total_count ?? 0),
            totalLateFees: (float) ($totals->total_late_fees ?? 0),
            totalOverdueAmount: (float) ($totals->total_overdue_amount ?? 0),
            defaultedLoans: $defaulted_count,
            activeLoans: $active_loans,
            totalPortfolio: (float) $total_portfolio,
        );
    }

    /**
     * Retrieve the Aging Report.
     *
     * @return AgingReportDTO The aging report.
     */
    public function getAgingReport(): AgingReportDTO
    {
        $buckets = [
            '0_30' => ['label' => '0-30 days', 'range' => [0, 30]],
            '31_60' => ['label' => '31-60 days', 'range' => [31, 60]],
            '61_90' => ['label' => '61-90 days', 'range' => [61, 90]],
            '90+' => ['label' => '90+ days', 'range' => [91, PHP_INT_MAX]],
        ];
        $data = [];
        $total_overall = Installment::where('status', 'overdue')->sum('due_amount');

        foreach ($buckets as $key => $bucket) {
            $range = $bucket['range'];
            $query = Installment::where('status', 'overdue')
                ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN ? AND ?', $range);

            $count = $query->count();
            $total_amount = $query->sum('due_amount');
            $total_late_fees = $query->sum('late_fee');

            $data[$key] = AgingBucketDTO::fromRawData(
                label: $bucket['label'],
                count: $count,
                totalAmount: (float) $total_amount,
                totalLateFees: (float) $total_late_fees,
                totalOverall: $total_overall,
            );
        }

        return AgingReportDTO::fromBuckets(
            current: $data['0_30'],
            thirtyToSixty: $data['31_60'],
            sixtyToNinety: $data['61_90'],
            ninetyPlus: $data['90+'],
        );
    }
}
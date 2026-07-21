<?php

namespace App\Domain\Receivables\ValueObjects;

use App\Http\Requests\Receivables\OverdueRequest;
use Carbon\Carbon;

final class OverdueFilterData
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * OverdueFilterData constructor for the class
     *
     * @param ?int $daysOverdue The number of days overdue
     * @param ?int $loanAccountID The ID of the loan account
     * @param ?int $borrowerID The ID of the borrower
     * @param int $perPage The number of results per page
     * @param ?Carbon $cutoffDate The cutoff date
     * @param string $filterType The filter type
     * @param string $sortBy The field to sort by
     * @param string $sortDirection The sort direction
     * @return void
    */
    private function __construct(
        public readonly ?int $daysOverdue,
        public readonly ?int $loanAccountID,
        public readonly ?int $borrowerID,
        public readonly int $perPage,
        public readonly ?Carbon $cutoffDate,
        public readonly string $filterType,
        public readonly string $sortBy,
        public readonly string $sortDirection,
    ) {}

    /**
     * Create a new OverdueRequest instance from a request
     *
     * @param OverdueRequest $request The request to create an OverdueRequest instance from
     * @return self The new OverdueRequest instance
     */
    public static function fromRequest(OverdueRequest $request): self
    {
        $data = $request->validated();
        $cutoff_date = null;
        $filter_type = 'none';

        if (isset($data['days_overdue'])) {
            $cutoff_date = Carbon::now()->subDays((int) $data['days_overdue']);
            $filter_type = 'days';
        } elseif (isset($data['cutoff_date'])) {
            $cutoff_date = Carbon::parse($data['cutoff_date']);
            $filter_type = 'cutoff_date';
        }

        return new self(
            daysOverdue: $data['days_overdue'] ?? null,
            loanAccountID: $data['loan_account_id'] ?? null,
            borrowerID: $data['borrower_id'] ?? null,
            perPage: (int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE),
            cutoffDate: $cutoff_date,
            filterType: $filter_type,
            sortBy: $data['sort_by'] ?? 'due_date',
            sortDirection: $data['sort_direction'] ?? 'asc',
        );
    }

    /**
     * Check if the OverdueRequest instance uses a date filter
     *
     * @return bool True if the OverdueRequest instance uses a date filter, false otherwise
     */
    public function usesDateFilter(): bool
    {
        return $this->filterType !== 'none';
    }
}
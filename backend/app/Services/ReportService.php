<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;

use App\Contracts\Services\ReportServiceInterface;
use App\DataTransferObjects\ReportFilterData;
use App\Models\Installment;
use App\Models\LoanApplication;
use App\Models\User;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ReportService implements ReportServiceInterface
{
    private const CACHE_TTL = 300; // 5 minutes
    private const NPA_DAYS = 90; // 3 months
    private const LOAN_APP_STATUS = ['approved', 'disbursed', 'rejected', 'cancelled', 'closed', 'under_review'];
    private const LOAN_INS_STATUS = ['overdue'];
    private const LOAN_ACC_STATUS = ['active'];
    private const COLLECTION_TARGET = ['max_score' => 50, 'target_value' => 70];
    private const NPA_TARGET = ['threshold' => 5, 'max_score' => 30, 'decrease_factor' => 10];
    private const DISBURSEMENT_TARGET = ['max_score' => 20, 'target_value' => 80];

    /**
     * Get dashboard metrics for report
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return array(
     *  disbursement => [
     *  - total_disbursed,
     *  - total_outstanding,
     *  - total_repaid,
     *  - disbursement_rate,
     *  - repayment_rate,
     *  - total_accounts ],
     *  loan_status => [
     *  - approved,
     *  - disbursed
     *  - rejected
     *  - cancelled
     *  - closed
     *  - under_review],
     *  collection => [
     *  - total_collected,
     *  - total_late_fee],
     *  npa => [
     *  - npa_amount
     *  - total_outstanding
     *  - npa_count
     *  - active_accounts_count
     *  - npa_percentage],
     *  health_score)
     */
    public function getDashboardMetrics(ReportFilterData $data, User $user): array
    {
        $cache_key = $this->generateCacheKey($data, $user);

        return Cache::remember($cache_key, self::CACHE_TTL, function () use ($data, $user) {
            $disbursement = $this->getDisbursementMetrics($data, $user);
            $loan_status = $this->getLoanStatusMetrics($data, $user);
            $collection = $this->getCollectionMetrics($data, $user);
            $npa = $this->getNpaMetrics($data, $user);

            $collection_rate = $disbursement['total_disbursed'] > 0 ?
                                 ($collection['total_collected'] / $disbursement['total_disbursed']) * 100
                                 : 0;

            // Weighted average: Health score (0 - 100)
            $health_score = $this->calculateHealthScore($collection_rate, $npa['npa_percentage'], $disbursement['disbursement_rate']);

            return [
                'disbursement' => $disbursement,
                'loan_status' => $loan_status,
                'collection' => $collection,
                'npa' => $npa,
                'health_score' => $health_score
            ];
        });
    }

    /**
     * Get approved loans with borrower, loan product, assigned officer details
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return LengthAwarePaginator
     */
    public function getApprovedLoans(ReportFilterData $data, User $user): LengthAwarePaginator
    {
        if (!$user->isManager() && !$user->isSupervisor()) throw new AuthorizationException('You are not authorized to perform this action.');

        if (is_null($user->branch_id)) throw new \Exception('No bank branch assigned for the user.');

        $query = LoanApplication::with(['borrower', 'loanProduct', 'assignedOfficer'])
                 ->select([
                    'id',
                    'application_ref',
                    'amount',
                    'approved_at',
                    'bank_branch',
                    'status',
                    'borrower_id',
                    'loan_product_id',
                    'assigned_officer_id',
                  ])
                  ->where('status', self::LOAN_APP_STATUS[0]);

        $this->applyBranchFilter($query, $user);

        if ($data->startDate) {
            $query->whereDate('approved_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->where('approved_at', '<=', $data->endDate);
        }

        return $query->orderByDesc('approved_at')->paginate(config('helper.default_pagination_length'));
    }


    /**
     * Get NPA loans with borrower, loan product details
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return LengthAwarePaginator
     */
    public function getNpaLoans(ReportFilterData $data, User $user): LengthAwarePaginator
    {
        if (!$user->isManager() && !$user->isSupervisor()) throw new AuthorizationException('You are not authorized to perform this action.');

        if (is_null($user->branch_id)) throw new \Exception('No bank branch assigned for the user.');

        $npa_sub_query = self::getNpaQuery();

        $query = LoanApplication::from('loan_applications AS lapp')
                  ->select([
                    'lapp.id',
                    'lapp.application_uuid',
                    'lapp.application_ref',
                    'lapp.amount',
                    'lapp.bank_branch',
                    'lapp.created_at',
                    'lapp.borrower_id',
                    'lapp.loan_product_id',
                    'la.disbursed_amount',
                    'la.outstanding_balance',
                  ])
                  ->with(['borrower', 'loanProduct'])
                  ->join('loan_accounts AS la', 'lapp.id', '=', 'la.loan_application_id')
                  ->leftJoinSub($npa_sub_query, 'npa_i', 'la.id', '=', 'npa_i.loan_account_id')
                  ->where('la.status', self::LOAN_ACC_STATUS[0]);

        $this->applyBranchFilter($query, $user, 'lapp');

        if ($data->startDate) {
            $query->whereDate('lapp.approved_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->whereDate('lapp.approved_at', '<=', $data->endDate);
        }

        return $query->orderByDesc('lapp.approved_at')->paginate(config('helper.default_pagination_length'));
    }

    /**
     * a collection of approved loan applications based on the provided filter data and user if the user is a manager
     *
     * @param ReportFilterData $data The filter data for the loan applications.
     * @param User $user The user making the request.
     * @return Collection|null The collection of approved loan applications, or null if the user is not a manager.
     */
    public function exportApprovedLoans(ReportFilterData $data, User $user): Collection|null
    {
        if (!$user->isManager()) return null;

        $query = LoanApplication::with(['borrower', 'loanProduct', 'assignedOfficer'])
                  ->where('status', self::LOAN_APP_STATUS[0]);

        $this->applyBranchFilter($query, $user);

        if ($data->startDate) {
            $query->where('approved_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->where('approved_at', '>=', $data->endDate);
        }

        return $query->orderByDesc('approved_at')->get();
    }


    // Helper functions:

    /**
     * Apply branch filter: if user is not headquarter manager, apply branch filter
     *
     * @param Builder $query
     * @param User $user
     * @param string $tableAlias
     * @return void
     */
    private function applyBranchFilter(QueryBuilder|Builder $query, User $user, string $tableAlias = 'loan_applications'): void
    {
        if ($user->isHeadquarterManager()) return;
        $query->where($tableAlias . '.bank_branch', $user->bank_branch);
    }

    /**
     * Generate cache key based on: start date, end date, user id, bank branch id
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return string
     */
    private function generateCacheKey(ReportFilterData $data, User $user): string
    {
        $details = [
            $data->startDate ? Carbon::parse($data->startDate)->format('Y-m-d') : 'NA_startDate',
            $data->endDate ? Carbon::parse($data->endDate)->format('Y-m-d') : 'NA_endDate',
            $user->id,
            $user->bank_branch ?? 'NA_branch'
        ];

        return 'dashboard_metrics_' . md5(implode('|', $details), false);
    }


    /**
     * Get the disbursement metrics for a given ReportFilterData and User.
     *
     * @param ReportFilterData $data The ReportFilterData object containing the filter data.
     * @param User $user The User object representing the user.
     * @return array(
     * - total_approved' => float,
     * - total_disbursed' => float,
     * - total_outstanding' => float,
     * - account_count' => int,
     * - total_repaid' => float,
     * - disbursement_rate' => float,
     * - repayment_rate' => float
     * )
     */
    private function getDisbursementMetrics(ReportFilterData $data, User $user): array
    {
        $query = LoanApplication::from('loan_applications AS lapp')
                 ->leftJoin('loan_accounts AS la', 'lapp.id', '=', 'la.loan_application_id')
                 ->whereIn('lapp.status', [
                    self::LOAN_APP_STATUS[0],
                    self::LOAN_APP_STATUS[1]
                 ]);

        $this->applyBranchFilter($query, $user, 'lapp');

        if ($data->startDate) {
            $query->whereDate('lapp.created_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->whereDate('lapp.created_at', '<=', $data->endDate);
        }

        // metrics result => total_approved, total_disbursed, total_outstanding, count of total accounts
        $metrics = $query->selectRaw('
            SUM(
                CASE WHEN lapp.status = "approved" THEN
                    lapp.amount
                    ELSE 0 END) AS total_approved,
            SUM(
                CASE WHEN lapp.status = "disbursed" THEN
                    la.disbursed_amount
                    ELSE 0 END) AS total_disbursed,
            SUM(
                CASE WHEN lapp.status = "disbursed" THEN
                    la.outstanding_balance
                    ELSE 0 END) AS total_outstanding,
            COUNT(
                DISTINCT CASE WHEN lapp.status = "disbursed" THEN
                    lapp.id END) AS account_count
        ')->first();

        $result = [
            'total_approved' => (float) $metrics->total_approved ?? 0,
            'total_disbursed' => (float) $metrics->total_disbursed ?? 0,
            'total_outstanding' => (float) $metrics->total_outstanding ?? 0,
            'account_count' => (int) $metrics->account_count
        ];


        $result['total_repaid'] = $result['total_disbursed'] - $result['total_outstanding'];
        $result['disbursement_rate'] = $result['total_approved'] > 0 ?
                                        ($result['total_disbursed'] / $result['total_approved']) * 100
                                        : 0;
        $result['repayment_rate'] = $result['total_disbursed'] > 0 ?
                                    ($result['total_repaid'] / $result['total_disbursed']) * 100
                                    : 0;
        return $result;
    }

    /**
     * Get the loan status metrics for a given ReportFilterData and User
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return array(
     * - approved,
     * - disbursed,
     * - under_review,
     * - rejected,
     * - cancelled,
     * - closed), which are all integers
     */
    private function getLoanStatusMetrics(ReportFilterData $data, User $user): array
    {
        $query = LoanApplication::query();
        $this->applyBranchFilter($query, $user);

        if ($data->startDate) {
            $query->whereDate('created_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->whereDate('created_at', '<=', $data->endDate);
        }

        $loan_statuses = $query->selectRaw('status, COUNT(id) AS count')
                         ->groupBy('status')
                         ->pluck('count', 'status')
                         ->toArray();

        $result = [];

        foreach (self::LOAN_APP_STATUS as $status) {
            $result[$status] = $loan_statuses[$status] ?? 0;
        }

        return $result;
    }

    /**
     * Get the collection metrics for a given ReportFilterData and User
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return array(
     * - total_collected => float,
     * - total_late_fee: float)
     */
    private function getCollectionMetrics(ReportFilterData $data, User $user): array
    {
        $query = Installment::from('installments AS i')
                 ->join('loan_accounts AS la', 'i.loan_account_id', '=', 'la.id')
                 ->join('loan_applications AS lapp', 'la.loan_application_id', '=', 'lapp.id');

        $this->applyBranchFilter($query, $user, 'lapp');

        if ($data->startDate) {
            $query->whereDate('i.paid_date', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->whereDate('i.paid_date', '<=', $data->endDate);
        }

        $result = [
            'total_collected' => (float) $query->sum('i.paid_amount'),
            'total_late_fee' => (float) $query->sum('i.late_fee'),
        ];

        return $result;
    }

    /**
     * Get the NPA metrics for a given ReportFilterData and User
     *
     * @param ReportFilterData $data
     * @param User $user
     * @return array(
     * - npa_amount => float,
     * - total_outstanding => float,
     * - npa_count => int)
     */
    private function getNpaMetrics(ReportFilterData $data, User $user): array
    {
        $npa_query = self::getNpaQuery();

        $query = LoanApplication::
                selectRaw('
                    SUM(
                        CASE WHEN npa_i.loan_account_id IS NOT NULL THEN
                            la.outstanding_balance
                            ELSE 0 END
                    ) AS npa_amount,
                    SUM( la.outstanding_balance ) AS total_outstanding,
                    COUNT(
                        DISTINCT CASE WHEN npa_i.loan_account_id IS NOT NULL THEN la.id END
                    ) AS npa_count,
                    COUNT(DISTINCT la.id) AS active_accounts_count
                ')
                 ->from('loan_applications AS lapp')
                 ->join('loan_accounts AS la', 'lapp.id', '=', 'la.loan_application_id')
                 ->leftJoinSub($npa_query, 'npa_i', 'la.id', '=', 'npa_i.loan_account_id')
                 ->where('la.status', self::LOAN_ACC_STATUS[0]);

        $this->applyBranchFilter($query, $user, 'lapp');

        if ($data->startDate) {
            $query->whereDate('lapp.created_at', '>=', $data->startDate);
        }

        if ($data->endDate) {
            $query->whereDate('lapp.created_at', '<=', $data->endDate);
        }
        $metrics = $query->first();

        $result = [
            'npa_amount' => (float) $metrics->npa_amount,
            'total_outstanding' => (float) $metrics->total_outstanding,
            'npa_count' => (int) $metrics->npa_count,
            'active_accounts_count' => (int) $metrics->active_accounts_count
        ];

        $result['npa_percentage'] = $metrics->total_outstanding > 0 ?
                                     round( ($metrics->npa_amount / $metrics->total_outstanding) * 100, 2)
                                     : 0;
        return $result;
    }

    /**
     * Get the NPA query, where NPA is defined as an installment that is more than NPA_DAYS days late
     *
     * @return Builder
     */
    private function getNpaQuery(): Builder
    {
        return Installment::query()->select('loan_account_id')
                ->where('status', self::LOAN_INS_STATUS[0])
                ->whereRaw('DATEDIFF(NOW(), due_date) > ?', [self::NPA_DAYS])
                ->groupBy('loan_account_id');
    }

    /**
     * Calculates the health score based on the collection rate, NPA percentage, and disbursement rate.
     *
     * - collection_score = min(max_score, (collectionRate / target_value) * max_score)
     * - npa_score = npaPercentage <= threshold ? max_score : max(0, max_score - ((npaPercentage - threshold) / (max_score - threshold)) * decrease_factor)
     * - disbursement_score = min(max_score, (disbursementRate / target_value) * max_score)
     *
     * @param float $collectionRate The collection rate.
     * @param float $npaPercentage The NPA percentage.
     * @param float $disbursementRate The disbursement rate.
     * @return int The calculated health score: (collection_score + npa_score + disbursement_score).
     */
    private function calculateHealthScore(float $collectionRate, float $npaPercentage, float $disbursementRate): int
    {
        // The rate: target > 70% - full score (50% weight)
        $collection_score = min(
            self::COLLECTION_TARGET['max_score'],
            ($collectionRate / self::COLLECTION_TARGET['target_value']) * self::COLLECTION_TARGET['max_score']
        );

        // NPA: target < 5% - full score (30% weight)
        $npa_score = $npaPercentage <= self::NPA_TARGET['threshold']
            ? self::NPA_TARGET['max_score']
            : max(
                0,
                self::NPA_TARGET['max_score'] - (($npaPercentage - self::NPA_TARGET['threshold']) / self::NPA_TARGET['threshold']) * self::NPA_TARGET['decrease_factor']
        );

        // Disbursement: target > 80% - full score (20% weight)
        $disbursement_score = min(
            self::DISBURSEMENT_TARGET['max_score'],
            ($disbursementRate / self::DISBURSEMENT_TARGET['target_value']) * self::COLLECTION_TARGET['max_score']
        );

        return (int) round($collection_score + $npa_score + $disbursement_score);
    }
}
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\User;
use App\Mail\WeeklyReportMail;

class GenerateWeeklyReport implements ShouldQueue
{
    private const TOP_PRODUCT_LIMIT = 5;

    use Queueable, Dispatchable, SerializesModels, InteractsWithQueue;

    private const LOAN_APPLICATION_STATUS = ['pending', 'approved', 'rejected', 'disbursed'];
    public function __construct() {}

    /**
     * Retrieve data for the current week and generates a report containing information:
     * - applications,
     * - loans
     * - payments
     * - customers
     * - and loan capital. And then, the report is then inserted into database.
     * Secondly, the report is sent it to system managers via email.
     *
     * @return void
     * @throws Exception if there is an error sending the email
     */
    public function handle(): void
    {
        $start_date = self::getStartDate();
        $end_date = self::getEndDate();

        $account_query = LoanAccount::whereBetween('created_at', [$start_date, $end_date]);

        $report_data = [
            'period' => $start_date->format('d M') . ' - ' . $end_date->format('d M, Y'),
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'applications' => $this->getApplications($start_date, $end_date),
            'loans' => [
                'total_disbursed' => $account_query->sum('disbursed_amount'),
                'total_outstanding' => $account_query->sum('outstanding_balance'),
                'new_accounts' => $account_query->count(),
            ],
            'payments' => $this->getPayments($start_date, $end_date),
            'customers' => $this->getCustomers($start_date, $end_date),
            'loan_capital' => $this->getProducts($start_date, $end_date),
        ];

        DB::table('weekly_reports')->insert([
            'report_data' => json_encode($report_data),
            'period_start' => $start_date,
            'end_date' => $end_date,
        ]);

        $system_managers = User::getSystemManagers()->get();

        foreach ($system_managers as $manager) {
            try {
                Mail::to(
                    $manager->email,
                )->send(
                    new WeeklyReportMail($report_data)
                );
            } catch (Exception $e) {
                Log::error('Failed to send weekly report to (' . $manager->login . '): ' . $manager->email, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'exception_class' => get_class($e),
                ]);
            }
        }
    }

    public static function getStartDate(): Carbon
    {
        return Carbon::now()->subWeek()->startOfWeek();
    }

    public static function getEndDate(): Carbon
    {
        return Carbon::now()->subWeek()->endOfWeek();
    }

    /**
     * Retrieve the count of loan applications within a specified date range and categorizes them by status.
     *
     * @param Carbon $startDate The start date of the date range.
     * @param Carbon $endDate The end date of the date range.
     * @return array (The array keys are 'total', 'submitted', 'approved', 'rejected', 'disbursed')
     */
    private function getApplications(Carbon $startDate, Carbon $endDate): array
    {
        $application_query = LoanApplication::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total' => $application_query->count(),
            'submitted' => $application_query->where('status', self::LOAN_APPLICATION_STATUS[0])->count(),
            'approved' => $application_query->where('status', self::LOAN_APPLICATION_STATUS[1])->count(),
            'rejected' => $application_query->where('status', self::LOAN_APPLICATION_STATUS[2])->count(),
            'disbursed' => $application_query->where('status', self::LOAN_APPLICATION_STATUS[3])->count(),
        ];
    }

    /**
     * Retrieve the total weekly payments and total weekly overdue payments within a specified date range.
     *
     * @param Carbon $startDate The start date of the date range.
     * @param Carbon $endDate The end date of the date range.
     * @return array (The array keys are 'weekly_payments' and 'weekly_overdue').
     */
    private function getPayments(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('installments')
            ->select(
                DB::raw('SUM(CASE WHENE paid_date BETWEEN ? AND ? THEN paid_amount ELSE 0 END) AS weekly_payments'),
                DB::raw('SUM(CASE WHEN status = "overdue" and due_date BETWEEN ? AND ? THEN due_amount ELSE 0 END) AS weekly_overdue'),
            )
            ->addBinding([$startDate, $endDate, $startDate, $endDate])
            ->toArray();
    }

    /**
     * Retrieve the count of:
     *  - new customers,
     *  - active customers,
     *  - total customers within a specified date range.
     *
     * @param Carbon $startDate The start date of the date range.
     * @param Carbon $endDate The end date of the date range.
     * @return array
     */
    private function getCustomers(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'new_customers' => DB::table('borrowers')
                                ->whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_customers' => DB::table('borrowers')
                                ->whereBetween('updated_at', [$startDate, $endDate])->count(),
            'total_customers' => DB::table('borrowers')->count(),
        ];
    }

    /**
     * Get five (5) highest ranking loan types based on the highest number of applications and total amount
     *
     * @return array
     */
    private function getProducts(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('loan_applications AS la')
                ->join('loan_products AS lp', 'la.loan_product_id', '=', 'lp.id')
                ->whereBetween('la.created_at', [$startDate, $endDate])
                ->select(
                    'lp.name',
                    DB::raw('COUNT(la.id) AS applications'),
                    DB::raw('SUM(la.amount) AS total_amount')
                )->groupBy('lp.id', 'lp.name')
                ->orderByDesc('applications')
                ->limit(self::TOP_PRODUCT_LIMIT)
                ->toArray();
    }
}
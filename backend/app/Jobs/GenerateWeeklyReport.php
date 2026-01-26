<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Mail\WeeklyReportMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateWeeklyReport implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $start_date = now()->subWeek()->startOfWeek();
        $end_date = now()->subWeek()->endOfWeek();

        $report_data = [
            'period' => $start_date->format('d M') . ' - ' . $end_date->format('d M, Y'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'applications' => [
                'total' => LoanApplication::whereBetween('created_at', [$start_date, $end_date])->count(),
                'submitted' => LoanApplication::whereBetween('created_at', [$start_date, $end_date])->where('status', 'submitted')->count(),
                'approved' => LoanApplication::whereBetween('created_at', [$start_date, $end_date])->where('status', 'approved')->count(),
                'rejected' => LoanApplication::whereBetween('created_at', [$start_date, $end_date])->where('status', 'rejected')->count(),
                'disbursed' => LoanApplication::whereBetween('created_at', [$start_date, $end_date])->where('status', 'disbursed')->count()
            ],
            'loans' => [
                'total_disbursed' => LoanAccount::whereBetween('created_at', [$start_date, $end_date])->sum('disbursed_amount'),
                'total_outstanding' => LoanAccount::sum('outstanding_balance'),
                'new_accounts' => LoanAccount::whereBetween('created_at', [$start_date, $end_date])->count(),
            ],
            'payments' => DB::table('installments')
                ->select(
                    DB::raw('SUM(CASE WHEN paid_date BETWEEN ? AND ? THEN paid_amount ELSE 0 END) as weekly_collection)'),
                    DB::raw('SUM(CASE WHEN status = "overdue" AND due_date BETWEEN ? AND ? THEN due_amount ELSE 0 END) as weekly_overdue')
                )
                ->addBinding([$start_date, $end_date, $start_date, $end_date])
                ->first(),
            'users' => [
                'new_customers' => User::where('role', 'customer')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->count(),
                'total_customers' => User::where('role', 'customer')->count(),
            ],
            'top_products' => DB::table('loan_applications AS la')
                ->join('loan_products AS lp', 'la.loan_product_id','=','lp.id')
                ->whereBetween('la.created_at', [$start_date, $end_date])
                ->select(
                    'lp.name',
                    DB::raw('COUNT(la.id) as applications'),
                    DB::raw('SUM(la.amount) as total_amount')
                )
                ->groupBy('lp.id', 'lp.name')
                ->orderByDesc('applications')
                ->limit(5)
                ->get(),
        ];

        DB::table('weekly_reports')->insert([
            'report_data' => json_encode($report_data),
            'period_start' => $start_date,
            'period_end' => $end_date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $system_admins = User::where('role', 'admin')->where('is_verified', true)->get();

        foreach ($system_admins as $admin) {
            try {
                Mail::to($admin->email)->send(new WeeklyReportMail($report_data));
            } catch (\Exception $e) {
                Log::error("Failed to send weekly report to {$admin->login}: {$e->getMessage()}");
            }
        }
    }
}

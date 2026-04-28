<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateMonthlyStatements;
use App\Jobs\GenerateWeeklyReport;
use App\Jobs\SendInstallmentReminders;

use App\Models\Installment;
use App\Models\LoanApplication;

use App\Notifications\InstallmentDueReminder;
use App\Notifications\StuckApplicationNotification;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\AuthenticateApi::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'borrower' => \App\Http\Middleware\BorrowerMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return \App\Exceptions\ApiExceptionHandler::handle($e, $request);
            }
            return null;
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        // $schedule->call(function () {
        //     $installments = Installment::with(['loanAccount.loanApplication.user'])
        //         ->where('status', 'pending')
        //         ->where('due_date', today()->addDays(3))
        //         ->get();

        //     foreach ($installments as $installment) {
        //         if ($installment->loanAccount->loanApplication->user) {
        //             $installment->loanAccount->loanApplication->user->notify(
        //                 new InstallmentDueReminder($installment, 3)
        //             );
        //         }
        //     }

        //     $due_today = Installment::with(['loanAccount.loanApplication.user'])
        //         ->where('status', 'pending')
        //         ->where('due_date', today())
        //         ->get();

        //     foreach ($due_today as $installment) {
        //         if ($installment->loanAccount->loanApplication->user) {
        //             $installment->loanAccount->loanApplication->user->notify(
        //                 new InstallmentDueReminder($installment, 0)
        //             );
        //         }
        //     }

        //     Installment::where('due_date', '<', today())
        //         ->where('status', 'pending')
        //         ->each(function ($installment) {
        //             $installment->update(['status' => 'overdue']);
        //             if ($installment->late_fee == 0) {
        //                 $loanProduct = $installment->loanAccount->loanApplication->loanProduct;
        //                 $installment->addLateFee($loanProduct->late_fee);
        //             }
        //         });

        //     Log::info('Daily installment schedule executed at ' . now());
        // })->dailyAt('09:00')->name('installment-reminders')->onOneServer();

        // $schedule->call(function () {
        //         $job = new SendInstallmentReminders();
        //         $job->handle();
        //     })
        //     ->dailyAt('09:00')
        //     ->name('installment-reminders')
        //     ->onOneServer()
        //     ->withoutOverlapping()
        //     ->appendOutputTo(storage_path('logs/schedule_' . Carbon::now()->format('d_m_Y')  .'.log'));

        // $schedule->job(new GenerateWeeklyReport())
        //     ->weekly()
        //     ->mondays()
        //     ->at('08:00')
        //     ->name('weekly-report')
        //     ->onOneServer()
        //     ->withoutOverlapping()
        //     ->appendOutputTo(storage_path('logs/weekly-report_'. Carbon::now()->format('d_m_Y') .'.log'));

        // $schedule->job(new GenerateMonthlyStatements())
        //     ->monthlyOn(1, '00:00')
        //     ->name('monthly-statements')
        //     ->onOneServer()
        //     ->withoutOverlapping()
        //     ->appendOutputTo(storage_path('logs/monthly-statements_'. Carbon::now()->format('d_m_Y') .'.log'));

        // $schedule->call(function(): void {
        //     $stuck_applications = LoanApplication::with(['assignedOfficer'])
        //         ->where('status', 'under_review')
        //         ->where('updated_at', '<', Carbon::now()->subDays(3))
        //         ->get();

        //     foreach ($stuck_applications as $stuck_application) {
        //         if ($officer = $stuck_application->officer) {
        //             $officer->notify(new StuckApplicationNotification($stuck_application, 3));
        //         }
        //     }
        // })
        // ->hourly()
        // ->name('stuck-applications-checker');

    })->create();
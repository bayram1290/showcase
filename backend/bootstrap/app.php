<?php

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
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
            'staff' => \App\Http\Middleware\StaffMiddleware::class,
            'borrower' => \App\Http\Middleware\BorrowerMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
        ]);

        /*
        // Registeration mobile validation middleware
        $middleware->alias([
            'validate.mobile' => \App\Http\Middleware\ValidateMobile::class,
        ]);

        // Or register mobile validation middleware to specific groups (like, to apply to all API routes)
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ValidateMobile::class,
        ]);
        */
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->renderable(function (Throwable $e, Illuminate\Http\Request $request): JsonResponse {
            return \App\Exceptions\ApiExceptionHandler::handle($e,  $request);
        });

        $exceptions->report(function (ModelNotFoundException $e) {
            \Log::warning('Model not found', [
                'model' => $e->getModel(),
                'ids' => $e->getIds(),
            ]);
        });

        $exceptions->report(function (Illuminate\Validation\ValidationException $e) {
            \Log::info('Validation failed', [
                'errors' => $e->errors(),
            ]);
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->call(function () {
            $installments = Installment::with(['loanAccount.loanApplication.user'])
                ->where('status', 'pending')
                ->where('due_date', today()->addDays(3))
                ->get();

            foreach ($installments as $installment) {
                if ($installment->loanAccount->loanApplication->user) {
                    $installment->loanAccount->loanApplication->user->notify(
                        new InstallmentDueReminder($installment, 3)
                    );
                }
            }

            $due_today = Installment::with(['loanAccount.loanApplication.user'])
                ->where('status', 'pending')
                ->where('due_date', today())
                ->get();

            foreach ($due_today as $installment) {
                if ($installment->loanAccount->loanApplication->user) {
                    $installment->loanAccount->loanApplication->user->notify(
                        new InstallmentDueReminder($installment, 0)
                    );
                }
            }

            Installment::where('due_date', '<', today())
                ->where('status', 'pending')
                ->each(function ($installment) {
                    $installment->update(['status' => 'overdue']);
                    if ($installment->late_fee == 0) {
                        $loanProduct = $installment->loanAccount->loanApplication->loanProduct;
                        $installment->addLateFee($loanProduct->late_fee);
                    }
                });

            Log::info('Daily installment schedule executed at ' . now());
        })->dailyAt('09:00')->name('installment-reminders')->onOneServer();

        $schedule->call(function () {
                $job = new SendInstallmentReminders();
                $job->handle();
            })
            ->dailyAt('09:00')
            ->name('installment-reminders')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/schedule_' . Carbon::now()->format('d_m_Y')  .'.log'));

        $schedule->job(new GenerateWeeklyReport())
            ->weekly()
            ->mondays()
            ->at('08:00')
            ->name('weekly-report')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/weekly-report_'. Carbon::now()->format('d_m_Y') .'.log'));

        $schedule->job(new GenerateMonthlyStatements())
            ->monthlyOn(1, '00:00')
            ->name('monthly-statements')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monthly-statements_'. Carbon::now()->format('d_m_Y') .'.log'));

        $schedule->call(function(): void {
            $stuck_applications = LoanApplication::with(['assignedOfficer'])
                ->where('status', 'under_review')
                ->where('updated_at', '<', Carbon::now()->subDays(3))
                ->get();

            foreach ($stuck_applications as $stuck_application) {
                if ($officer = $stuck_application->officer) {
                    $officer->notify(new StuckApplicationNotification($stuck_application, 3));
                }
            }
        })
        ->hourly()
        ->name('stuck-applications-checker');

    })->create();
<?php


use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Jobs\ProcessDailyInstallmentsJob;
use App\Jobs\GenerateMonthlyStatements;
use App\Jobs\GenerateWeeklyReport;
use App\Jobs\CheckExpiringNegotiationsJob;
use App\Jobs\EnforceExpiredNegotiationsJob;

use App\Console\Commands\MakeCustomMigration;

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

        // Daily installment reminders
        $schedule->job(new ProcessDailyInstallmentsJob())
                ->dailyAt('09:00')
                ->name('installment-reminders-to-borrowers')
                ->timezone('Asia/Ashgabat')
                ->onFailure(function (Throwable $exception) {
                    $error_message = 'An error occurred while processing daily installment reminders: ' . $exception->getMessage();
                    $error_trace = $exception->getTraceAsString();
                    Log::error(
                        $error_message,
                        [
                            'trace' => $error_trace,
                            'exception_class' => get_class($exception),
                            'exception_message' => $exception->getMessage(),
                        ]
                    );
                })->onOneServer()
                ->withoutOverlapping();

        // Weekly report (queued job)
        $schedule->job(new GenerateWeeklyReport())
            ->sundays()
            ->at('23:00')
            ->timezone('Asia/Ashgabat')
            ->withoutOverlapping()
            ->name('weekly-report')
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while generating weekly report: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            })
            ->onOneServer();

        // Monthly statements (queued job)
        $schedule->job(new GenerateMonthlyStatements())
            ->monthlyOn(1, '00:30')
            ->timezone('Asia/Ashgabat')
            ->withoutOverlapping()
            ->name('monthly-statements')
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while generating monthly statements: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            })
            ->onOneServer();

        // Stuck applications (under_review for about 3 days & more, and notify assigned officer)
        $schedule->command('application:check-stuck-applications')
            ->weekdays()
            ->at('06:00')
            ->timezone('Asia/Ashgabat')
            ->withoutOverlapping()
            ->name('stuck-applications-checker')
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while checking for stuck applications: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            });

        $schedule->command('documents:purge-deleted')
            ->dailyAt('03:00')
            ->onOneServer()
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while purging soft-deleted documents: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            });

        $schedule->command('document:scan-poll')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while polling for document scans: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            });

        $schedule->command('document:scan-cleanup')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer()
            ->onFailure(function (Throwable $exception) {
                $error_message = 'An error occurred while cleaning up document scans: ' . $exception->getMessage();
                $error_trace = $exception->getTraceAsString();
                Log::error(
                    $error_message,
                    [
                        'trace' => $error_trace,
                        'exception_class' => get_class($exception),
                        'exception_message' => $exception->getMessage(),
                    ]
                );
            });

        $schedule->job(new CheckExpiringNegotiationsJob())->dailyAt('09:00');
        $schedule->job(new EnforceExpiredNegotiationsJob())->dailyAt('00:30');
    })
    ->withCommands([
        MakeCustomMigration::class,
    ])
    ->create();
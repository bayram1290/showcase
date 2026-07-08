<?php

namespace App\Providers;


use App\Contracts\Services\CreditCheckServiceInterface;
use App\Services\CreditCheckService;
use App\Contracts\Repositories\CreditCheckRepositoryInterface;
use App\Repositories\CreditCheckRepository;
use App\Contracts\Services\ApprovalWorkflowServiceInterface;
use App\Services\ApprovalWorkflowService;
use App\Contracts\Services\DisbursementServiceInterface;
use App\Services\DisbursementService;
use App\Contracts\Repositories\LoanAccountRepositoryInterface;
use App\Repositories\LoanAccountRepository;
use App\Contracts\Services\ReportServiceInterface;
use App\Services\ReportService;
use App\Contracts\Services\LoanPerformanceServiceInterface;
use App\Services\LoanPerformanceService;
use App\Contracts\Services\DocumentServiceInterface;
use App\Services\DocumentService;
use App\Models\User;

use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CreditCheckServiceInterface::class, CreditCheckService::class);
        $this->app->bind(CreditCheckRepositoryInterface::class, CreditCheckRepository::class);
        $this->app->bind(ApprovalWorkflowServiceInterface::class, ApprovalWorkflowService::class);
        $this->app->bind(DisbursementServiceInterface::class, DisbursementService::class);
        $this->app->bind(LoanAccountRepositoryInterface::class, LoanAccountRepository::class);
        $this->app->bind(ReportServiceInterface::class, ReportService::class);
        $this->app->bind(LoanPerformanceServiceInterface::class, LoanPerformanceService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService ::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-report-dashboard', [ReportPolicy::class, 'viewDashboard']);
        Gate::define('view-report-approved-loans', [ReportPolicy::class, 'viewApprovedLoans']);
        Gate::define('view-report-npa', [ReportPolicy::class, 'viewNpa']);
        Gate::define('export-approved-reports', [ReportPolicy::class, 'exportApprovedReports']);
        Gate::define('view-performance', [LoanAccountRepository::class, 'viewPerformance']);

        // Rate Limiter for upload document
        RateLimiter::for('documents.upload', function () {
            $request = request();
            $application_id = $request->route('application')?->id ?? 0;
            $user_id = $request->user()?->id ?? 0;
            $key = "document_upload:app_{$application_id}:user_{$user_id}";

            $store = Cache::store();
            $is_redis = $store->getStore() instanceof RedisStore;
            $limits = [];

            $limits = self::setRateLimitingRate(
                $request->user() instanceof User,
                $is_redis,
                $key
            );

            return array_map(function (Limit $limit) {
                return $limit->response(function () use ($limit) {

                    $retry_after_in_seconds = $limit->decaySeconds ?? 60;
                    return ApiResponse::error(
                        'Upload limit exceeded. Please wait before trying again.',
                        'RATE_LIMIT_EXCEEDED',
                        Response::HTTP_TOO_MANY_REQUESTS
                    )->header('Retry-After', $retry_after_in_seconds);
                });
            }, $limits);

        });

        // Rate Limiter for virustotal
        RateLimiter::for(config('services.virustotal.rate_key'), function () {
            $per_minute = (int) config('services.virustotal.polls_per_minute', 4);
            return Limit::perMinute($per_minute);
        });
    }

    /**
     * Set the rate limiting rate based on the user type and caching mechanism.
     *
     * - If the user is a staff user:
     *      return rate limiting policies with higher limits.
     * - If the caching mechanism is Redis,
     *      return rate limiting policies with higher precision.
     * - Otherwise, it return rate limiting policies with lower precision.
     *
     * @param bool $isStaffUser Whether the user is a staff user.
     * @param bool $isRedis Whether the caching mechanism is Redis.
     * @param string $key The key for the rate limiting policy.
     * @return array The array of rate limiting policies.
     */
    private function setRateLimitingRate(bool $isStaffUser, bool $isRedis, string $key): array
    {
        if ($isStaffUser) {
            /*
            * @future – Staff upload support (not yet implemented, I am kind of planning in future)
            * If staff are allowed to upload documents on behalf of borrowers
            * (e.g., scanned copies, internally generated documents), we can enable
            * this block by uncommenting it and updating the policy accordingly.
            */
            return [
                Limit::perMinute(9)->by($key),
                Limit::perHour(18)->by($key),
                Limit::perDay(36)->by($key),
            ];
        } else if ($isRedis) {
            // Redis-specific sliding windows (more precise)
            return [
                Limit::perMinute(3)->by($key),
                Limit::perHour(9)->by($key),
                Limit::perDay(15)->by($key),
            ];

        } else {
            // Fallback to file/array cache (less precise but working scenario)
            return [
                Limit::perMinute(6)->by($key),
                Limit::perHour(15)->by($key),
                Limit::perDay(25)->by($key),
            ];
        }
    }
}

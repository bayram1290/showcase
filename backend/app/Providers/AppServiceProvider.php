<?php

namespace App\Providers;

use App\Contracts\Services\CreditCheckServiceInterface;
use App\Services\CreditCheckService;
use App\Contracts\Repositories\CreditCheckRepositoryInterface;
use App\Repositories\CreditCheckRepository;
use App\Contracts\Services\ApprovalWorkflowServiceInterface;
use App\Services\ApprovalWorkflowService;

use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

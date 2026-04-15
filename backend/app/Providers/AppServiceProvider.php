<?php

namespace App\Providers;

use App\Repositories\CreditCheckRepository;
use App\Contracts\Repositories\CreditCheckRepositoryInterface;
use App\Contracts\Services\CreditCheckServiceInterface;
use App\Services\CreditCheckService;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

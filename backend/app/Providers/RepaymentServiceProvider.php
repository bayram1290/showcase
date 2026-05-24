<?php

namespace App\Providers;

use App\Contracts\Services\RepaymentServiceInterface;
use App\Services\RepaymentService;
use Illuminate\Support\ServiceProvider;

class RepaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(RepaymentServiceInterface::class, RepaymentService::class);
    }
}

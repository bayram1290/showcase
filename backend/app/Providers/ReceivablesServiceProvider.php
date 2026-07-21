<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Application\Receivables\ReceivablesService;
use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Domain\Receivables\Contracts\NegotiationRepositoryInterface;
use App\Domain\Receivables\Contracts\LoanAccountRepositoryInterface;
use App\Infrastructure\Persistence\NegotiationRepository;
use App\Infrastructure\Persistence\ReceivablesLoanAccountRepository;

class ReceivablesServiceProvider extends ServiceProvider
{
    /**
     * Register the bindings for the application's service container.
     */
    public function register(): void
    {
        $this->app->bind(ReceivablesServiceInterface::class, ReceivablesService::class);
        $this->app->bind(NegotiationRepositoryInterface::class, NegotiationRepository::class);
        $this->app->bind(LoanAccountRepositoryInterface::class, ReceivablesLoanAccountRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

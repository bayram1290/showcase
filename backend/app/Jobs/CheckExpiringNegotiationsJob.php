<?php

namespace App\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Domain\Receivables\Events\NegotiationExpiringEvent;
use App\Domain\Receivables\Contracts\NegotiationRepositoryInterface;

class CheckExpiringNegotiationsJob
{
    use Dispatchable;

    /**
     * Dispatch a NegotiationExpiringEvent for each negotiation that is expiring within the specified number of days.
     *
     * @param NegotiationRepositoryInterface $repository The negotiation repository used to retrieve expiring negotiations.
     * @param Dispatcher $eventDispatcher The event dispatcher used to dispatch the NegotiationExpiringEvent.
     * @return void
     */
    public function handle(NegotiationRepositoryInterface $repository, Dispatcher $eventDispatcher): void
    {
        $days = config('receivables.negotiation_expiry_warning_days', 3);
        foreach ($repository->getExpiringNegotiations($days) as $negotiation) {
            $eventDispatcher->dispatch(new NegotiationExpiringEvent($negotiation, $days));
        }
    }
}
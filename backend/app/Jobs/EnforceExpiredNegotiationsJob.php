<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

use App\Domain\Receivables\Contracts\NegotiationRepositoryInterface;
use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Models\Negotiation;
use App\Models\User;

class EnforceExpiredNegotiationsJob
{
    use Dispatchable;

    /**
     * Handles the job of enforcing the expiration of negotiations that have expired.
     *
     * @param NegotiationRepositoryInterface $repository The negotiation repository interface.
     * @param ReceivablesServiceInterface $service The receivables service interface.
     * @return void
     * @throws \Exception If an error occurs during the defaulting process.
     * @return void
     */
    public function handle(NegotiationRepositoryInterface $repository, ReceivablesServiceInterface $service): void
    {
        $expired = Negotiation::where('is_active', true)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $negotiation) {
            $repository->markAsExpired($negotiation);
            $loan_account = $negotiation->loanAccount;

            if ($loan_account && $loan_account->status !== 'defaulted') {
                $system_user = User::where('role', 'system')->first();
                if ($system_user) {
                    try {
                        $service->markDefault($loan_account, $system_user, 'Negotiation expired without resolution.');
                    } catch (\Exception $e) {
                        Log::error('Failed to default loan after negotiation expiry', [
                            'loan_account_id' => $loan_account->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }
}
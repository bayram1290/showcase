<?php

namespace App\Services;

use App\Contracts\Repositories\LoanAccountRepositoryInterface;
use App\Contracts\Services\DisbursementServiceInterface;
use App\DataTransferObjects\DisbursementData;
use App\Exceptions\DisbursementException;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;
use App\Events\LoanDisbursement as LoanDisbursementEvent;
use Illuminate\Support\Facades\Log;

class DisbursementService implements DisbursementServiceInterface
{

    private const LOAN_APROVED_STATUS_STR = 'approved';
    private const LOAN_DISBURSED_STATUS_STR = 'disbursed';
    private const LOAN_ACCOUNT_NUMBER_PREFIX = 'TKM-';
    private const LOAN_ACCOUNT_NUMBER_LENGTH = 8;
    private const LOAN_ACCOUNT_ACTIVE_STATUS_STR = 'active';
    private const LOAN_INSTALLMENT_PENDING_STATUS_STR = 'pending';

    public function __construct(
        protected LoanAccountRepositoryInterface $repository
    ) {}

    public function disburse(DisbursementData $data): LoanAccount
    {
        try {
            $application = LoanApplication::with('loanProduct')->findOrFail($data->loanApplicationID);

            if ($application->status !== self::LOAN_APROVED_STATUS_STR) {
                throw new DisbursementException('Application is not satisfied for disbursement.');
            }

            Gate::authorize('disburseApplication', $application);

            if ($application->status !== self::LOAN_APROVED_STATUS_STR) {
                throw new DisbursementException('Application is not satisfied for disbursement.');
            }

            if ($application->loanAccount()->exists()) {
                throw new DisbursementException('Application has already been disbursed.');
            }

            return DB::transaction(function () use ($data, $application) {
                $unique_account_number = $this->generateUniqueAccountNumber();

                $loan_account = $this->repository->create([
                    'account_number' => $unique_account_number,
                    'loan_application_id' => $application->id,
                    'disbursed_amount' => $application->amount,
                    'outstanding_balance' => $application->amount,
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'installments_paid' => 0,
                    'next_installment_date' => Carbon::now()->addMonth(),
                    'status' => self::LOAN_ACCOUNT_ACTIVE_STATUS_STR,
                ]);

                $this->generateAmortizationSchedule($loan_account, $application);

                $application->update([
                    'status' => self::LOAN_DISBURSED_STATUS_STR,
                    'disbursed_at' => Carbon::now(),
                ]);

                DB::afterCommit(function () use ($loan_account, $data) {
                    event(new LoanDisbursementEvent($loan_account, $data->disbursedByUserID, $data->remarks));
                });

                Log::info('Loan disbursed', [
                    'loan_application_id' => $data->loanApplicationID,
                    'account_number' => $loan_account->account_number,
                    'amount' => $application->amount,
                    'disbursed_by_user' => $data->disbursedByUserID,
                ]);

                return $loan_account;
            });

        } catch (DisbursementException $disbursement_exception) {
            throw $disbursement_exception;
        } catch (Throwable $th) {
            Log::error('Disbursement failed', [
                'loan_application_id' => $data->loanApplicationID,
                'error_message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            throw new DisbursementException('Disbursement failed: ' . $th->getMessage(), 0, $th);
        }
    }

    /**
     * Generate a unique loan account number by appending a random string to a prefix.
     *
     * @return string The unique loan account number.
     */
    private function generateUniqueAccountNumber(): string
    {
        $max_attempts = 10;
        for ($iter = 0; $iter < $max_attempts; $iter++) {
            $unique_str = Str::upper(Str::random(self::LOAN_ACCOUNT_NUMBER_LENGTH));
            $unique_number_candidate = self::LOAN_ACCOUNT_NUMBER_PREFIX . $unique_str;

            if (!$this->repository->findByAccountNumber($unique_number_candidate)) {
                return $unique_number_candidate;
            }
        }

        return self::LOAN_ACCOUNT_NUMBER_PREFIX . Carbon::now()->timestamp . Str::random(2);
    }
    /**
     * Generate an amortization schedule for a loan account based on the loan application details.
     *
     * @param LoanAccount $loan_account The loan account for which the amortization schedule is generated.
     * @param LoanApplication $application The loan application details.
     * @return void
     */
    private function generateAmortizationSchedule(LoanAccount $loan_account, LoanApplication $application): void
    {
        $principal = $application->amount;
        $monthly_rate = $application->interest_rate / 100 / 12;
        $tenure = $application->tenure;
        $monthly_installment = $application->monthly_installment;

        $remaining_principal = $principal;
        $due_date = Carbon::now()->addMonth();

        for ($iter = 1; $iter <= $tenure; $iter++) {
            $interest_amount = $remaining_principal * $monthly_rate;
            $principal_amount = $monthly_installment - $interest_amount;

            // Last installment adjustment
            if ($iter === $tenure) {
                $principal_amount = $remaining_principal;
                $interest_amount = $monthly_installment - $principal_amount;

                if ($interest_amount < 0) $interest_amount = 0;
            }

            $loan_account->installments()->create([
                'installment_number' => $iter,
                'due_date' => $due_date,
                'due_amount' => $monthly_installment,
                'principal_amount' => $principal_amount,
                'interest_amount' => $interest_amount,
                'status' => self::LOAN_INSTALLMENT_PENDING_STATUS_STR,
            ]);

            $remaining_principal -= $principal_amount;
            $due_date = $due_date->addMonth();
        }
    }
}
<?php

namespace App\Domain\Receivables\ValueObjects;

use App\Models\Installment;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

final class OverdueInstallmentDTO implements Arrayable
{
    /**
     * OverdueInstallmentDTO constructor for the class
     *
     * @param int $id The ID of the installment
     * @param int $installmentNumber The installment number
     * @param Money $dueAmount The due amount
     * @param Money $lateFee The late fee
     * @param Money $totalDue The total due
     * @param int $daysOverdue The number of days overdue
     * @param Carbon $dueDate The due date
     * @param ?Carbon $paidDate The paid date
     * @param string $status The status
     * @param string $accountNumber The account number
     * @param string $borrowerName The borrower name
     * @param int $borrowerId The borrower ID
     * @param ?string $phone The phone number
     * @param ?string $email The email address
     * @param ?string $loanProductName The loan product name
     * @return void
    */
    private function __construct(
        public readonly int $id,
        public readonly int $installmentNumber,
        public readonly Money $dueAmount,
        public readonly Money $lateFee,
        public readonly Money $totalDue,
        public readonly int $daysOverdue,
        public readonly Carbon $dueDate,
        public readonly ?Carbon $paidDate,
        public readonly string $status,
        public readonly string $accountNumber,
        public readonly string $borrowerName,
        public readonly int $borrowerId,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $loanProductName,
    ) {}

    /**
     * Create a new Installment instance from a model
     *
     * @param Installment $installment The model to create an Installment instance from
     * @return self The new Installment instance
     */
    public static function fromModel(Installment $installment): self
    {
        $loanAccount = $installment->loanAccount;
        $application = $loanAccount->loanApplication;
        $borrower = $application->borrower;

        return new self(
            id: $installment->id,
            installmentNumber: $installment->installment_number,
            dueAmount: Money::fromDecimal((float) $installment->due_amount),
            lateFee: Money::fromDecimal((float) $installment->late_fee),
            totalDue: Money::fromDecimal((float) $installment->due_amount + (float) $installment->late_fee),
            daysOverdue: Carbon::parse($installment->due_date)->diffInDays(now()),
            dueDate: Carbon::parse($installment->due_date),
            paidDate: $installment->paid_date ? Carbon::parse($installment->paid_date) : null,
            status: $installment->status,
            accountNumber: $loanAccount->account_number,
            borrowerName: $borrower->getFullName(),
            borrowerId: $borrower->id,
            phone: $borrower->phone,
            email: $borrower->email,
            loanProductName: $application->loanProduct->name ?? null,
        );
    }

    /**
     * Create an array of Installment instances from a collection of models
     *
     * @param Collection $installments The collection of models to create Installment instances from
     * @return array An array of Installment instances
    */
    public static function collectionFromCollection($installments): array
    {
        return $installments->map(fn ($i) => self::fromModel($i))->toArray();
    }

    /**
     * Convert the Installment instance to an array
     *
     * @return array The array representation of the Installment instance
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installmentNumber,
            'due_amount' => $this->dueAmount->toArray(),
            'late_fee' => $this->lateFee->toArray(),
            'total_due' => $this->totalDue->toArray(),
            'days_overdue' => $this->daysOverdue,
            'due_date' => $this->dueDate->toDateString(),
            'paid_date' => $this->paidDate?->toDateString(),
            'status' => $this->status,
            'loan_account' => ['account_number' => $this->accountNumber],
            'borrower' => [
                'id' => $this->borrowerId,
                'name' => $this->borrowerName,
                'phone' => $this->phone,
                'email' => $this->email,
            ],
            'loan_product' => $this->loanProductName,
        ];
    }
}
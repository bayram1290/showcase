<?php

namespace App\DataTransferObjects;

use App\Http\Requests\InstallmentRepaymentRequest;
use App\Models\Borrower;
use App\Models\Installment;

final class RepaymentData
{
    /**
     * Repayment Data Transfer Object constructor.
     *
     * @param int $repaymentUUID The repayment UUID.
     * @param int $userID The user ID.
     * @param string $repaymentExecutant The repayment executant.
     * @param float $amount The repayment amount.
     * @param int $installmentRepaymentMethodID The repayment method ID.
     * @param string|null $remarks The remarks.
     */
    private function __construct(
        public readonly string $repaymentUUID,
        public readonly int $userID,
        public readonly string $repaymentExecutant,
        public readonly float $amount,
        public readonly int $installmentRepaymentMethodID,
        public readonly ?string $remarks,
    ) {}

    /**
     * Create a new RepaymentData instance from an InstallmentRepaymentRequest.
     *
     * @param InstallmentRepaymentRequest $request The repayment request.
     * @return self The created RepaymentData instance.
     */
    public static function fromRequest(InstallmentRepaymentRequest $request, Installment $installment): self
    {
        return new self(
            repaymentUUID: $installment->installment_uuid,
            userID: $request->user()->id,
            repaymentExecutant: $request->user() instanceOf Borrower ? 'Borrower':'System User',
            amount: $request->validated('amount'),
            installmentRepaymentMethodID: $request->validated('repayment_method_id'),
            remarks: $request->validated('remarks'),
        );
    }
}
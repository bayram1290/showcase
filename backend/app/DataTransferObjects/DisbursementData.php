<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DisbursementData
{
    /**
     * @param int $loanApplicationID The ID of the loan application.
     * @param int $disbursedByUserID The ID of the user who disbursed the loan.
     * @param string|null $remarks The remarks for the disbursement (optional).
     */
    private function __construct(
        public readonly int $loanApplicationID,
        public readonly int $disbursedByUserID,
        public readonly ?string $remarks = null
    ) {}

    /**
     * Create an instance of the class from the given request data.
     *
     * @param array $data The request data to be validated.
     * @return self The instance of the class with the validated data.
     * @throws ValidationException If the request data fails validation.
     */
    public static function fromRequest(array $data): self
    {
        $validated = Validator::make($data, [
            'loan_application_id' => 'required|integer|exists:loan_applications,id',
            'disbursed_by_user_id' => 'required|integer|exists:users,id',
            'remarks' => 'nullable|string|max:500',
        ])->validate();

        return new self(
            $validated['loan_application_id'],
            $validated['disbursed_by_user_id'],
            $validated['remarks'] ?? null
        );
    }

    /**
     * Convert the object to an array.
     *
     * @return array The array representation of the object.
     */
    public function toArray(): array
    {
        return [
            'loan_application_id' => $this->loanApplicationID,
            'disbursed_by_user_id' => $this->disbursedByUserID,
            'remarks' => $this->remarks,
        ];
    }
}
<?php

namespace App\DataTransferObjects;

class CreditCheckData
{
    /**
     * Constructs a new CreditCheckData object or simply DTO.
     *
     * @param int $loanApplicationId The ID of the loan application to check the credit for.
     * @param int|null $checkedByUserId [optional] The ID of the user who checked the credit. Defaults to null.
     */
    public function __construct(
        public readonly int $loanApplicationId,
        public readonly ?int $checkedByUserId = null,
    ) {}
}

<?php

namespace App\DataTransferObjects;

class ApprovalData
{
    /**
     * Constructor for ApprovalData.
     *
     * @param int $loanApplicationID The ID of the loan application.
     * @param int $userID The ID of the user.
     * @param string|null $remarks Additional remarks (optional).
     */
    public function __construct(
        public readonly int $loanApplicationID,
        public readonly int $userID,
        public readonly ?string $remarks
    ) {}
}
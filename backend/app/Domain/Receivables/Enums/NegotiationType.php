<?php

namespace App\Domain\Receivables\Enums;

use App\Domain\Receivables\Strategies\NegotiationStrategy;
use App\Domain\Receivables\Strategies\NewScheduleStrategy;
use App\Domain\Receivables\Strategies\DiscountStrategy;
use App\Domain\Receivables\Strategies\ExtensionStrategy;
use App\Domain\Receivables\Strategies\SettlementStrategy;
use App\Models\LoanAccount;
use App\Domain\Receivables\ValueObjects\Money;

enum NegotiationType: string
{
    case NEW_SCHEDULE = 'new_schedule';
    case DISCOUNT = 'discount';
    case EXTENSION = 'extension';
    case SETTLEMENT = 'settlement';

    /**
     * Retrieve the default expiration days for the current state.
     *
     * @return int Returns the number of days for the default expiration.
     */
    public function getDefaultExpirationDays(): int
    {
        return match ($this) {
            self::NEW_SCHEDULE => 30,
            self::DISCOUNT => 15,
            self::EXTENSION => 20,
            self::SETTLEMENT => 10,
        };
    }

    /**
     * Check if the loan is valid for the current state.
     *
     * @param LoanAccount $loan The loan account.
     * @return bool Returns `true` if the loan is valid for the current state, `false` otherwise.
     */
    public function isValidForLoan(LoanAccount $loan): bool
    {
        return match ($this) {
            self::NEW_SCHEDULE => in_array($loan->status, ['active', 'paused']),
            self::DISCOUNT => $loan->status === 'active' && $loan->outstanding_balance > 0,
            self::EXTENSION => $loan->status === 'active' && $loan->outstanding_balance > 0,
            self::SETTLEMENT => in_array($loan->status, ['active', 'paused', 'defaulted']),
        };
    }

    /**
     * Check if the current state requires approval.
     *
     * @return bool Returns `true` if the current state requires approval, `false` otherwise.
     */
    public function requiresApproval(): bool
    {
        return match ($this) {
            self::NEW_SCHEDULE => false,
            self::DISCOUNT => true,
            self::EXTENSION => false,
            self::SETTLEMENT => true,
        };
    }

    /**
     * Retrieve the negotiation strategy for the current state.
     *
     * @return NegotiationStrategy The negotiation strategy for the current state.
     */
    public function getStrategy(): NegotiationStrategy
    {
        return match ($this) {
            self::NEW_SCHEDULE => new NewScheduleStrategy(),
            self::DISCOUNT => new DiscountStrategy(),
            self::EXTENSION => new ExtensionStrategy(),
            self::SETTLEMENT => new SettlementStrategy(),
        };
    }

    /**
     * Get the label for the current state.
     *
     * @return string The label for the current state.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::NEW_SCHEDULE => 'New Payment Schedule',
            self::DISCOUNT => 'Principal Discount',
            self::EXTENSION => 'Loan Term Extension',
            self::SETTLEMENT => 'Settlement Offer',
        };
    }

    /**
     * Get the applicable fee for the current state and loan.
     *
     * @param LoanAccount $loan The loan account.
     * @return Money|null The applicable fee for the current state and loan, or `null` if no fee is applicable.
     */
    public function getApplicableFee(LoanAccount $loan): ?Money
    {
        return match ($this) {
            self::NEW_SCHEDULE, self::DISCOUNT, self::SETTLEMENT => null,
            self::EXTENSION => Money::fromDecimal(50.00),
        };
    }
}
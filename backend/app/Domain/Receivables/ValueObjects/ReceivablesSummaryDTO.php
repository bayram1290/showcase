<?php

namespace App\Domain\Receivables\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class ReceivablesSummaryDTO implements Arrayable
{
    /**
     * Create a new OverdueAggregates instance.
     *
     * @param int $totalOverdueInstallments The total number of overdue installments.
     * @param Money $totalLateFees The total amount of late fees.
     * @param Money $totalOverdueAmount The total amount of overdue loans.
     * @param int $defaultedLoans The total number of defaulted loans.
     * @param float $portfolioAtRiskPercentage The percentage of the portfolio at risk.
     * @param float $collectionEffectivenessIndex The collection effectiveness index.
     * @param int $activeLoans The total number of active loans.
     * @param Money $totalLoanPortfolioAmount The total loan portfolio amount.
     * @param string $asOf The date as of which the data is valid.
     * @return void
     */
    private function __construct(
        public readonly int $totalOverdueInstallments,
        public readonly Money $totalLateFees,
        public readonly Money $totalOverdueAmount,
        public readonly int $defaultedLoans,
        public readonly float $portfolioAtRiskPercentage,
        public readonly float $collectionEffectivenessIndex,
        public readonly int $activeLoans,
        public readonly Money $totalLoanPortfolioAmount,
        public readonly string $asOf,
    ) {}

    /**
     * Create a new OverdueAggregates instance from aggregates.
     *
     * @param int $totalOverdueInstallments The total number of overdue installments.
     * @param float $totalLateFees The total amount of late fees.
     * @param float $totalOverdueAmount The total amount of overdue loans.
     * @param int $defaultedLoans The total number of defaulted loans.
     * @param int $activeLoans The total number of active loans.
     * @param float $totalPortfolio The total loan portfolio amount.
     * @return self The new OverdueAggregates instance.
     */
    public static function fromAggregates(
        int $totalOverdueInstallments,
        float $totalLateFees,
        float $totalOverdueAmount,
        int $defaultedLoans,
        int $activeLoans,
        float $totalPortfolio,
    ): self {
        $portfolioAtRisk = $totalPortfolio > 0 ? ($totalOverdueAmount / $totalPortfolio) * 100 : 0;
        $collectionIndex = $totalPortfolio > 0 ? (($totalPortfolio - $totalOverdueAmount) / $totalPortfolio) * 100 : 0;

        return new self(
            totalOverdueInstallments: $totalOverdueInstallments,
            totalLateFees: Money::fromDecimal($totalLateFees),
            totalOverdueAmount: Money::fromDecimal($totalOverdueAmount),
            defaultedLoans: $defaultedLoans,
            portfolioAtRiskPercentage: round($portfolioAtRisk, 2),
            collectionEffectivenessIndex: round($collectionIndex, 2),
            activeLoans: $activeLoans,
            totalLoanPortfolioAmount: Money::fromDecimal($totalPortfolio),
            asOf: now()->toDateString(),
        );
    }

    /**
     * Convert the OverdueAggregates instance to an array.
     *
     * @return array The array representation of the OverdueAggregates instance.
     */
    public function toArray(): array
    {
        return [
            'total_overdue_installments' => $this->totalOverdueInstallments,
            'total_late_fees' => $this->totalLateFees->toArray(),
            'total_overdue_amount' => $this->totalOverdueAmount->toArray(),
            'defaulted_loans' => $this->defaultedLoans,
            'portfolio_at_risk_percentage' => $this->portfolioAtRiskPercentage,
            'collection_effectiveness_index' => $this->collectionEffectivenessIndex,
            'active_loans' => $this->activeLoans,
            'total_loan_portfolio_amount' => $this->totalLoanPortfolioAmount->toArray(),
            'as_of' => $this->asOf,
        ];
    }
}
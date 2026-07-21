<?php

namespace App\Domain\Receivables\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class AgingReportDTO implements Arrayable
{

    /**
     * Construct a new AgingReportDTO instance
     *
     * @param AgingBucketDTO $current The current aging bucket
     * @param AgingBucketDTO $thirtyToSixty The 31-60 days aging bucket
     * @param AgingBucketDTO $sixtyToNinety The 61-90 days aging bucket
     * @param AgingBucketDTO $ninetyPlus The 90+ days aging bucket
     * @param Money $totalAmount The total amount of loans
     * @param int $totalInstallments The total number of installments
     * @param string $asOf The date of the report
     * @return void
    */
    private function __construct(
        public readonly AgingBucketDTO $current,
        public readonly AgingBucketDTO $thirtyToSixty,
        public readonly AgingBucketDTO $sixtyToNinety,
        public readonly AgingBucketDTO $ninetyPlus,
        public readonly Money $totalAmount,
        public readonly int $totalInstallments,
        public readonly string $asOf,
    ) {}

    /**
     * Create a new AgingReportDTO instance from aging buckets
     *
     * @param AgingBucketDTO $current The current aging bucket
     * @param AgingBucketDTO $thirtyToSixty The aging bucket for 31-60 days
     * @param AgingBucketDTO $sixtyToNinety The aging bucket for 61-90 days
     * @param AgingBucketDTO $ninetyPlus The aging bucket for 90+ days
     * @return self The new AgingReportDTO instance
     */
    public static function fromBuckets(
        AgingBucketDTO $current,
        AgingBucketDTO $thirtyToSixty,
        AgingBucketDTO $sixtyToNinety,
        AgingBucketDTO $ninetyPlus,
    ): self {
        $total_amount = $current->totalAmount
            ->add($thirtyToSixty->totalAmount)
            ->add($sixtyToNinety->totalAmount)
            ->add($ninetyPlus->totalAmount);

        $totalInstallments = $current->count + $thirtyToSixty->count + $sixtyToNinety->count + $ninetyPlus->count;

        return new self(
            current: $current,
            thirtyToSixty: $thirtyToSixty,
            sixtyToNinety: $sixtyToNinety,
            ninetyPlus: $ninetyPlus,
            totalAmount: $total_amount,
            totalInstallments: $totalInstallments,
            asOf: now()->toDateString(),
        );
    }

    /**
     * Convert the AgingReportDTO instance to an array
     *
     * @return array The array representation of the AgingReportDTO instance
     */
    public function toArray(): array
    {
        return [
            '0_30_days' => $this->current->toArray(),
            '31_60_days' => $this->thirtyToSixty->toArray(),
            '61_90_days' => $this->sixtyToNinety->toArray(),
            '90_plus_days' => $this->ninetyPlus->toArray(),
            'total_amount' => $this->totalAmount->toArray(),
            'total_installments' => $this->totalInstallments,
            'as_of' => $this->asOf,
        ];
    }
}
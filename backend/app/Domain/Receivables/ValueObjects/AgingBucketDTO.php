<?php

namespace App\Domain\Receivables\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class AgingBucketDTO implements Arrayable
{
    /**
     * Construct a new AgingBucketDTO instance
     *
     * @param string $label The label of the aging bucket
     * @param int $count The count of loans in the aging bucket
     *  @param Money $totalAmount The total amount of loans in the aging bucket
     * @param Money $totalLateFees The total late fees in the aging bucket
     * @param float $percentage The percentage of the total amount in the aging bucket
     * @return void
    */
    public function __construct(
        public readonly string $label,
        public readonly int $count,
        public readonly Money $totalAmount,
        public readonly Money $totalLateFees,
        public readonly float $percentage,
    ) {}

    /**
     * Create a new AgingBucketDTO instance from raw data
     *
     * @param string $label The label of the aging bucket
     * @param int $count The count of loans in the aging bucket
     * @param float $totalAmount The total amount of loans in the aging bucket
     * @param float $totalLateFees The total late fees in the aging bucket
     * @param float $totalOverall The total amount of the aging bucket
     * @return self The new AgingBucketDTO instance
     */
    public static function fromRawData(string $label, int $count, float $totalAmount, float $totalLateFees, float $totalOverall): self
    {
        $percentage = $totalOverall > 0 ? ($totalAmount / $totalOverall) * 100 : 0;
        return new self(
            label: $label,
            count: $count,
            totalAmount: Money::fromDecimal($totalAmount),
            totalLateFees: Money::fromDecimal($totalLateFees),
            percentage: round($percentage, 2),
        );
    }

    /**
     * Convert the AgingBucketDTO instance to an array
     *
     * @return array The array representation of the AgingBucketDTO instance
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'count' => $this->count,
            'total_amount' => $this->totalAmount->toArray(),
            'total_late_fees' => $this->totalLateFees->toArray(),
            'percentage_of_total' => $this->percentage,
        ];
    }
}
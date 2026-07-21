<?php

namespace App\Domain\Receivables\ValueObjects;

use InvalidArgumentException;

final class Money
{
    /**
     * Construct a new Money instance
     *
     * @param int $cents The amount of cents
     * @throws InvalidArgumentException if the amount is negative
     * @return void
    */
    public function __construct(public readonly int $cents)
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    /**
     * Create a new Money instance from a decimal amount
     *
     * @param float $amount The decimal amount of money
     * @return self The new Money instance
     */
    public static function fromDecimal(float $amount): self
    {
        return new self((int) round($amount * 100));
    }

    /**
     * Convert the Money instance to a decimal amount
     *
     * @return float The decimal amount of money
     */
    public function toDecimal(): float
    {
        return $this->cents / 100;
    }

    /**
     * Add the amount of money from another Money instance
     *
     * @param Money $other The other Money instance
     * @return self The new Money instance with the added amount
     */
    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Check if the Money instance is equal to another Money instance
     *
     * @param Money $other The other Money instance
     * @return bool True if the Money instances are equal, false otherwise
     */
    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Convert the Money instance to an array representation
     *
     * @return array The array representation of the Money instance
     */
    public function toArray(): array
    {
        return ['amount' => number_format($this->toDecimal(), 2), 'currency' => 'USD'];
    }
}
<?php

namespace App\Domain\Receivables\ValueObjects;

use App\Domain\Receivables\Exceptions\InvalidNegotiationNoteException;

final class NegotiationNote
{
    /**
     * Create a new NegotiationNote instance
     *
     * @param string $value The value of the negotiation note
     * @throws InvalidNegotiationNoteException If the negotiation note is empty, too short, or too long
     * @return self The new NegotiationNote instance
     */
    public function __construct(public readonly string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw InvalidNegotiationNoteException::empty();
        }
        if (strlen($trimmed) < 10) {
            throw InvalidNegotiationNoteException::tooShort();
        }
        if (strlen($trimmed) > 1000) {
            throw InvalidNegotiationNoteException::tooLong();
        }
    }

    /**
     * Create a new NegotiationNote instance from a string
     *
     * @param string $note The string value of the negotiation note
     * @return self The new NegotiationNote instance
     */
    public static function from(string $note): self
    {
        return new self($note);
    }
}
<?php

namespace App\Mail;

use App\Models\LoanAccount;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanDisbursedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new loan disbursed message instance.
     */
    public function __construct(
        public LoanAccount $loanAccount
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Loan Disbursement Confirmation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.loan-disbursed',
            with: $this->dataPayload()
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Retrieves the necessary data for the content and formats it into an associative array.
     * The data includes the borrower's name and gender, the loan application reference number, the account number,
     * the loan amount, the disbursement date, the next installment date, the monthly installment amount, and the tenure.
     *
     * @return array The data payload for the loan disbursed email.
     */

    protected function dataPayload(): array
    {
        $borrower = $this->loanAccount->loanApplication->borrower;
        $application = $this->loanAccount->loanApplication;

        return [
            'borrower_name' => $borrower->name . ' ' . $borrower->surname,
            'gender' => $borrower->gender ?? null,
            'application_ref' => $application->application_ref,
            'account_number' => $this->loanAccount->account_number,
            'amount' => number_format($application->amount, 2),
            'disbursement_date' => Carbon::now()->format('F j, Y'),
            'next_installment_date' => Carbon::parse($this->loanAccount->next_installment_date)?->format('F j, Y'),
            'monthly_installment' => $application->monthly_installment,
            'tenure' => $application->tenure ?? null,
        ];
    }
}

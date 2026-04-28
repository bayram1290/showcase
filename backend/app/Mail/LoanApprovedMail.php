<?php

namespace App\Mail;

use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct(public LoanApplication $application, public User $approver)
    {
        $this->data = [
            'borrower_name' => $application->borrower->getFullName(),
            'borrower_gender' => $application->borrower->gender,
            'loan_type' => $application->loan_type,
            'application_ref' => $application->application_ref,
            'amount' => number_format(floatval($application->amount), 2),
            'approver_name' => $approver->getFullName(),
            'approval_date' => Carbon::now()->format('F j, Y'),
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your loan application has been approved.',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.loan-approved',
        );
    }
}

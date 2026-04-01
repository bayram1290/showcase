<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public $statement_data;
    public $filepath;

    public function __construct(
        $statement_data,
        $filepath
    ) {
        $this->statement_data = $statement_data;
        $this->filepath = $filepath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Monthly loan system statement - {$this->statement_data['period']}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-statement',
            with: [
                'statement_data' => $this->statement_data,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorage($this->filepath),
        ];
    }
}

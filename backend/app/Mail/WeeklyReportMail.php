<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $report_data;

    public function __construct(
        $report_data
    ) {
        $this->report_data = $report_data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Weekly loan system report - {$this->report_data['period']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-report',
            with: [
                    'report_data' => $this->report_data,
                ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

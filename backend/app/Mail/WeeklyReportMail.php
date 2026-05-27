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

    public array $reportData;

    public function __construct(
        array $reportData
    ) {
        $this->reportData = $reportData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Weekly loan system report - {$this->reportData['period']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-report',
            with: [
                    'report_data' => $this->reportData,
                ]
        );
    }
}

<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanApplicationStatusUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The loan application instance.
     *
     * @var LoanApplication $application
     */
    public $application;

    /**
     * The old status of the loan application.
     *
     * @var string $old_status
     */
    public $old_status;

    /**
     * The new status of the loan application.
     *
     * @var string $new_status
     */
    public $new_status;


    /**
     * Create a new instance of the notification.
     *
     * @param LoanApplication $application
     * @param string $old_status
     * @param string $new_status
     * @return void
     */
    public function __construct(
        LoanApplication $application,
        $old_status,
        $new_status
    ) {
        $this->application = $application;
        $this->old_status = $old_status;
        $this->new_status = $new_status;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @param  object $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }


    /**
     * Build the mail representation of the notification.
     *
     * @param  object $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Loan application status update: {$this->application->application_ref}")
            ->greeting("Dear {$notifiable->name}!")
            ->line("Your loan application ({$this->application->application_ref}) status has been updated")
            ->line("***Old Status was: " . ucfirst(str_replace('_', ' ', $this->old_status)) . " ***")
            ->line("***New Status is: " . ucfirst(str_replace('_', ' ', $this->new_status)) . " ***")
            ->line("***Amount (in US dollar): $" . number_format($this->application->amount, 2) . " ***")
            ->line("***Tenure: {$this->application->tenure} in months")
            ->action("View application", url("/applications/{$this->application->id}"))
            ->line('Thank you for using our banking services!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  object $notifiable
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_ref' => $this->application->application_ref,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'amount' => $this->application->amount,
            'messages' => "Your loan application {$this->application->application_ref} status changed from {$this->old_status} to {$this->new_status}"
        ];
    }
}

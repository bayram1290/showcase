<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StuckApplicationNotification extends Notification
{
    use Queueable;

    public $application;
    public $days_stuck;

    public function __construct(
        LoanApplication $application,
        int $days_stuck = 3
    ) {
        $this->application = $application;
        $this->days_stuck = $days_stuck;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Stuck loan application: ' . $this->application->application_ref)
            ->greeting("Dear {$notifiable->name}!")
            ->line("A loan application assigned to you has been stuck in - UNDER REVIEW - status for {$this->days_stuck} days.")
            ->line("**Application Details:**")
            ->line("- Reference: {$this->application->application_ref}")
            ->line("- Customer: {$this->application->user->name}")
            ->line("- Amount: $" . number_format((float) $this->application->amount, 2))
            ->line("- Applied: {$this->application->created_at->format('d M Y')}")
            ->line("- Last updated: {$this->application->updated_at->format('d M Y')}")
            ->line("**Days in Review:** ")
            ->action('Review Application', url("/v1/applications/{$this->application->id}"))
            ->line('Please review this application at your earliest convenience.')
            ->line('Thank you for your attention to this matter.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_ref' => $this->application->application_ref,
            'customer_name' => $this->application->user->name,
            'amount' => $this->application->amount,
            'days_stuck' =>$this->days_stuck,
            'message' => "Application {$this->application->application_ref} has been stucj under review for {$this->days_stuck} days",
            'action_url' => "/v1/applications/{$this->application->id}"
        ];
    }
}

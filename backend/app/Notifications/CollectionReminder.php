<?php

namespace App\Notifications;

use App\Models\Installment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CollectionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected Installment $installment;

    public function __construct(Installment $installment)
    {
        $this->installment = $installment;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Generate the content of an email to be sent to a notifiable object.
     *
     * @param $notifiable The notifiable object.
     * @return MailMessage The email message.
     */
    public function toMail($notifiable): MailMessage
    {
        $loanAccount = $this->installment->loanAccount;
        $borrower = $loanAccount->loanApplication->borrower;

        return (new MailMessage)
            ->subject('Urgent: Your Loan Installment is Overdue')
            ->greeting("Dear {$borrower->getFullName()},")
            ->line("Your installment #{$this->installment->installment_number} for loan account {$loanAccount->account_number} is overdue.")
            ->line("Due amount: $" . number_format($this->installment->due_amount, 2))
            ->line("Late fee incurred: $" . number_format($this->installment->late_fee, 2))
            ->line("Please contact our collections team at +993 (12) 456-7890 to resolve this matter.")
            ->action('View Loan Details', url('/dashboard'))
            ->line('Thank you for your prompt attention.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param Notifiable $notifiable The notifiable object.
     * @return array
    */
    public function toArray($notifiable): array
    {
        return [
            'installment_id' => $this->installment->id,
            'due_amount' => $this->installment->due_amount,
            'late_fee' => $this->installment->late_fee,
            'message' => 'Loan installment is overdue. Please contact collections.',
        ];
    }
}
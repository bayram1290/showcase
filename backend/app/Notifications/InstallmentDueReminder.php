<?php

namespace App\Notifications;

use App\Models\Installment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class InstallmentDueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The installment to be reminded about
     *
     * @var Installment
     */
    public $installment;

    /**
     * The number of days until the installment is due
     *
     * @var int
     */
    public $days_until_due;

    /**
     * Create a new instance of the notification
     *
     * @param Installment $installment
     * @param int $days_until_due
     */
    public function __construct(
        Installment $installment,
        $days_until_due = 0
    ) {
        $this->installment = $installment;
        $this->days_until_due = $days_until_due;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  object  $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $due_date = Carbon::parse($this->installment->due_date)->format('d M Y');
        $amount = number_format((float) $this->installment->due_amount, 2);

        $message = (new MailMessage)
            ->subject("Loan installment reminder: {$amount} due on {$due_date}")
            ->greeting("Dear {$notifiable->name}!\n");

        $message->line($this->days_until_due > 0 ?
                "This is a just reminder that your loan installment of - $ {$amount} - is due in {$this->days_until_due} days on **{$due_date} **.":
                "Your loan installment of - $ {$amount} - is due today (**{$due_date} **)"
        );

        return $message->line("Installment details:")
            ->line("- Installment #: {$this->installment->installment_number}")
            ->line("- Due Amount: $ {$amount}")
            ->line("- Account number: {$this->installment->loanAccount->account_number}")
            ->action("Make payment:", url("/payments/{$this->installment->id}"))
            ->line("Please, ensure timely payment to avoid late fees.")
            ->line("Thank you for using our bank services!");
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  object  $notifiable
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        return [
            'installment_id' => $this->installment->id,
            'loan_account_id' => $this->installment->loan_account_id,
            'due_date' => Carbon::parse($this->installment->due_date)->toDateString(),
            'due_amount' => $this->installment->due_amount,
            'days_until_due' => $this->days_until_due,
            'message' => "Installment #{$this->installment->installment_number} is due on " . Carbon::parse($this->installment->due_date)->format('d M Y')
        ];
    }
}
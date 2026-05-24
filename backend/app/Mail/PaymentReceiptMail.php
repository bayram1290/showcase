<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use App\Models\Installment;
use App\Models\InstallmentRepaymentMethod;
use Carbon\Carbon;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new PaymentReceiptMail instance.
     *
     * @param Installment $installment The installment.
     * @param int $installmentRepaymentMethodID The repayment method ID.
     */
    public function __construct(
        protected Installment $installment,
        protected int $installmentRepaymentMethodID
    ) {}

    /**
     * Set the subject of the email.
     *
     * @return Envelope The envelope instance.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Loan Payment Receipt - Installment #{$this->installment->installment_number}"
        );
    }

     /**
     * Define the content of the email with the installment details and borrower information.
     *
     * @return Content The content instance.
     */
    public function content(): Content
    {

        $loan_account = $this->installment->loanAccount;
        $borrower = $loan_account->loanApplication->borrower;

        $honorific = strtolower($borrower->gender) === 'f' ? 'Ms./Mrs.' : 'Mr.';

        return new Content(
            markdown: 'emails.payment-receipt',
            with: [
                'honorific' => $honorific,
                'borrower_name' => $borrower->name . ' ' . $borrower->surname,
                'amount' => number_format(floatval($this->installment->due_amount), 2),
                'installment_number' => $this->installment->installment_number,
                'outstanding_balance' => number_format(floatval($loan_account->outstanding_balance), 2),
                'payment_date' => Carbon::parse($this->installment->paid_date)?->format('F j, Y'),
                'payment_method' => InstallmentRepaymentMethod::find($this->installmentRepaymentMethodID)?->name ?? null
            ]
        );
    }
}

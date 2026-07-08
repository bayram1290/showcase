<?php

namespace App\Services;

use App\Mail\NewApplicationSubmittedMail;
use App\Models\Borrower;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function applicationSubmitted(LoanApplication $application, Borrower $borrower): void
    {
        $this->sendGenericMail(
            $borrower->email,
            'New loan application submitted',
            [
                'message' => 'Your loan application has been submitted successfully. Our team will review it shortly.',
                'application_details' => $this->formatApplicationDetails($application, $borrower),
                'next_steps' => 'You will be notified by email or phone within 2-3 business days.'
            ],
            ['application_issue_id' => $application->application_uuid]
        );

        // Send email to assigned officer
        if ($application->assigned_officer_id) {
            $officer = User::find($application->assigned_officer_id);

            if ($officer && $officer->email) {
                $this->sendGenericMail(
                    $officer->email,
                    'New loan application submitted to YOU',
                    [
                        'message' => 'A new loan application has been assigned to you for review.',
                        'application_details' => $this->formatApplicationDetails($application, $borrower),
                        'action_required' => 'Please review this application at your earliest convenience.'
                    ],
                    ['application_issue_id' => $application->application_uuid]
                );
            }
        }

        if (config('helper.notify_admin_on_new_loan_submission')) {
            $admin_email = User::where('role', 'admin')->first()->email;
            if ($admin_email) {
                $this->sendGenericMail(
                    $admin_email,
                    'New loan application submitted',
                    [
                        'message' => 'A new loan application has been submitted.',
                        'application_details' => $this->formatApplicationDetails($application, $borrower),
                        'action_required' => 'For monitoring purposes only.'
                    ],
                    ['application_issue_id' => $application->application_uuid]
                );
            }
        }
    }

    /**
     * Format the loan application details into an array for email notification.
     *
     * @param LoanApplication $application
     * @param Borrower $borrower
     *
     * @return array
     */
    protected function formatApplicationDetails(LoanApplication $application, Borrower $borrower): array
    {
        return [
            'Application reference' => $application->application_ref,
            'Borrower name' => $borrower->gender === 'M' ? 'Mr. ': 'Ms./Mrs. ' . $borrower->first_name . ' ' . $borrower->last_name,
            'Loan amount' => $application->loan_amount,
            'Loan type' => $application->loanProduct->type ?? 'N/A',
            'Loan purpose' => $application->loan_purpose ?? 'N/A',
            'Loan term' => $application->tenure . ' months',
            'Submitted at' => $application->submitted_at ? Carbon::parse($application->submitted_at)->format('d-m-Y H:i:s') : Carbon::now()->format('d-m-Y H:i:s')
        ];
    }


    /**
     * Sends a generic email to the given recipient.
     *
     * @param string $recipient The recipient of the email.
     * @param string $subject The subject of the email.
     * @param mixed $content The content of the email.
     * @param array $data The data to be passed to the email view.
     * @param string $view The email view to use. If not provided, the default view will be used.
     *
     * @throws \Exception If the email sending fails.
     */
    protected function sendGenericMail(string $recipient, string $subject, $content, array $data = [], ?string $view = null): void
    {
        try {
            Mail::to($recipient)->send(new NewApplicationSubmittedMail(subject: $subject, content: $content, data: $data, view: $view));
            Log::info('Email sent: ', ['to' => $recipient, 'subject' => $subject]);
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'to' => $recipient,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
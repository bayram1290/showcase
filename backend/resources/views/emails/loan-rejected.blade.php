<x-mail::message>
# Loan Application Rejected

Dear <b>{{ strtolower($gender) === 'F' ? 'Ms./Mrs.' : 'Mr.' }}</b> {{ $borrower_name }},<br><br>

Subject: Update on your {{ $loan_type }} loan application

We regret to inform you that your loan application with reference <b>{{ $application_ref }}</b> for the amount of <b>{{ $amount }}</b> USD has been **rejected** after careful review.

**Rejected By:** <b>{{ $rejecter_name }}</b><br>
**Rejection Date:** <b>{{ $rejection_date }}</b>

@if($rejection_reason)
**Reason for rejection:**<br>
{{ $rejection_reason }}
@endif

If you have any questions, please contact our support team.

<x-mail::button :url="config('app.url') . '/contact'">
Contact Support
</x-mail::button>

Thank you for considering {{ config('app.name') }},<br>
We encourage you to apply again in the future.
</x-mail::message>
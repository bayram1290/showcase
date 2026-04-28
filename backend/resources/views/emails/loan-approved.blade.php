<x-mail::message>
# Loan Application Approved

Dear <b>{{ strtolower($gender) === 'F' ? 'Ms./Mrs.' : 'Mr.' }}</b> {{ $borrower_name }},<br><br>

Subject: Application for {{ $loan_type }} loan from the office.

Your loan application with application reference <b>{{ $application_ref }}</b> has been approved for the amount of <b>{{ $amount }}</b> in USD dollar.<br>

**Approver By:** <b>{{ $approver_name }}</b><br>
**Approvement Date:** <b>{{ $approval_date }}</b>

<x-mail::button :url="config('app.url') . '/dashboard'">
View Your Application
</x-mail::button>

Thanks for choosing {{ config('app.name') }},<br>
We look forward to working with you.
</x-mail::message>

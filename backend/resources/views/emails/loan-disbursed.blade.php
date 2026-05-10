<x-mail::message>
# Loan Disbursement Confirmation

Dear <b>{{ strtolower($gender) === 'f' ? 'Mrs./Mrs.': 'Mr.' }}</b> {{ $borrower_name }}

We are pleased to inform you that your loan application with reference <b>{{ $application_ref }}</b> for the amount of <b>{{ $amount }}</b> USD has been **disbursed**.

Loan details are as follows:
**Loan Account Number:** <b>{{ $account_number }}</b><br>
**Disbursed Amount:** <b>{{ $amount }}</b><br>
**Disbursement Date:** <b>{{ $disbursement_date }}</b><br>
**First Installment Due Date:** <b>{{ $next_installment_date }}</b><br>
**Monthly Installment:** <b>{{ $monthly_installment }}</b><br>
**Loan Term:** <b>{{ $tenure }}</b> in months<br>

<x-mail::button :url="config('app.url') . '/dashboard'">
    View Your Loan Account
</x-mail::button>

Thanks for choosing {{ config('app.name') }},<br>
We look forward to working with you.

Best regards,<br>
The {{ config('app.name') }} Team

</x-mail::message>
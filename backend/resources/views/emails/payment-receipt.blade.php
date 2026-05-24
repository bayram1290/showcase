<x-mail::message>
    <h3>Loan Payment Receipt</h3>

    <p>Dear {{ $honorific . ' ' . $borrower_name }},</p>
    <p>We have recieved your payment of <b>${{ $amount }}</b> for installment #<b>{{ $installment_number }}</b>. </p>

    <p>Here are your payment details:</p>
    <ul>
        <li><b>Amount Paid:</b> ${{ $amount }}</li>
        <li><b>Payment Date:</b> {{ $payment_date }}</li>
        @if ($payment_method)
            <li><b>Payment Method:</b> {{ $payment_method }}</li>
        @endif
        <li><b>Remaining Balance:</b> ${{ $outstanding_balance }}</li>
    </ul>

    <br>
    <br>
    <x-mail::button :url="config('app.url') . '/dashboard'">
        View Your Loan Account
    </x-mail::button>

    <p>Thanks for choosing {{ config('app.name') }},<br>We look forward to working with you.</p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>

</x-mail::message>
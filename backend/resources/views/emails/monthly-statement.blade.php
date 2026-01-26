<!DOCTYPE html>
<html>
<head>
    <title>Monthly Loan Statement</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #2c3e50; }
        .bank-info { color: #7f8c8d; font-size: 14px; }
        .customer-info { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #3498db; }
        .summary-card.highlight { border-left-color: #e74c3c; }
        .summary-value { font-size: 20px; font-weight: bold; color: #2c3e50; }
        .summary-label { font-size: 14px; color: #7f8c8d; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th, .table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .table th { background: #ecf0f1; font-weight: bold; }
        .status-paid { color: #27ae60; font-weight: bold; }
        .status-pending { color: #f39c12; font-weight: bold; }
        .status-overdue { color: #e74c3c; font-weight: bold; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #7f8c8d; text-align: center; }
        .note { background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bank Loan System</h1>
            <h2>Monthly Loan Statement</h2>
            <div class="bank-info">
                <p>123 Banking Street, Banking district, City 100001</p>
                <p>Phone: 123-456-7890 | Email: info@demo_a.com</p>
            </div>
        </div>

        <div class="customer-info">
            <h3>Account Statement</h3>
            <p><strong>Statement Period:</strong> {{ $statement_data['statement_period'] }}</p>
            <p><strong>Customer Name:</strong> {{ $statement_data['customer']['name'] }}</p>
            <p><strong>Account Number:</strong> {{ $statement_data['customer']['account_number'] }}</p>
            <p><strong>Statement Date:</strong> {{ $statement_data['generated_date'] }}</p>
        </div>

        <div class="note">
            <p><strong>Important:</strong> This statement should be reviewed with care. Any discrepancies are required to be reported within a 30-day period.</p>
        </div>

        <h3>Account Summary</h3>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-value">${{ number_format($statement_data['account_summary']['opening_balance'], 2) }}</div>
                <div class="summary-label">Opening Balance</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">${{ number_format($statement_data['account_summary']['closing_balance'], 2) }}</div>
                <div class="summary-label">Closing Balance</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">${{ number_format($statement_data['account_summary']['total_principal_paid'], 2) }}</div>
                <div class="summary-label">Principal Paid</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">${{ number_format($statement_data['account_summary']['total_interest_paid'], 2) }}</div>
                <div class="summary-label">Interest Paid</div>
            </div>
            @if($statement_data['account_summary']['total_late_fees'] > 0)
            <div class="summary-card highlight">
                <div class="summary-value">${{ number_format($statement_data['account_summary']['total_late_fees'], 2) }}</div>
                <div class="summary-label">Late Fees Charged</div>
            </div>
            @endif
        </div>

        <h3>Account Activity</h3>
        @if(count($statement_data['transactions']) > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount ($)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statement_data['transactions'] as $transaction)
                <tr>
                    <td>{{ $transaction['date'] }}</td>
                    <td>{{ $transaction['description'] }}</td>
                    <td>{{ $transaction['type'] }}</td>
                    <td>{{ number_format($transaction['amount'], 2) }}</td>
                    <td>
                        @if($transaction['status'] == 'paid')
                            <span class="status-paid">Paid</span>
                        @elseif($transaction['status'] == 'pending')
                            <span class="status-pending">Pending</span>
                        @elseif($transaction['status'] == 'overdue')
                            <span class="status-overdue">Overdue</span>
                        @else
                            {{ $transaction['status'] }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p>No transactions for this period.</p>
        @endif

        @if(count($statement_data['upcoming_payments']) > 0)
            <h3>Upcoming Payments</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Due Date</th>
                        <th>Installment #</th>
                        <th>Amount Due ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statement_data['upcoming_payments'] as $payment)
                    <tr>
                        <td>{{ $payment['due_date'] }}</td>
                        <td>{{ $payment['installment_number'] }}</td>
                        <td>{{ number_format($payment['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="footer">
            <p>This is a fully automated statement and requires no signature.</p>
            <p>For any questions, please contact our customer service team.</p>
            <p>Email: support@demo_a.com | Phone: 123-456-7890</p>
            <p>© {{ date('Y') }} Bank Loan System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
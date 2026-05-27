<!DOCTYPE html>
<html>
<head>
    <title>Weekly Loan System Report</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; }
        .section { margin-bottom: 30px; }
        .report-title { background: #3498db; color: white; padding: 10px; border-radius: 3px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .status-card { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .status-label { font-size: 14px; color: #7f8c8d; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .table th { background: #ecf0f1; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #7f8c8d; }
        .highlight { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bank Loan System</h1>
            <h2>Weekly Performance Report</h2>
            <p>Period: {{ $report_data['period'] }}</p>
            <p>Generated: {{ $report_data['generated_at'] }}</p>
        </div>

        <div class="content">
            <div class="section">
                <h3 class="report-title"> Application Statistics</h3>
                <div class="stats-grid">
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['applications']['total'] }}</div>
                        <div class="status-label">Total Applications</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['applications']['submitted'] }}</div>
                        <div class="status-label">Submitted</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['applications']['approved'] }}</div>
                        <div class="status-label">Approved</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['applications']['rejected'] }}</div>
                        <div class="status-label">Rejected</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['applications']['disbursed'] }}</div>
                        <div class="status-label">Disbursed</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3 class="report-title"> Financial Summary</h3>
                <div class="stats-grid">
                    <div class="status-card">
                        <div class="status-value">${{ number_format($report_data['loans']['total_disbursed'], 0) }}</div>
                        <div class="status-label">Weekly Disbursement</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">${{ number_format($report_data['payments']['weekly_payments'] ?? 0, 0) }}</div>
                        <div class="status-label">Weekly Collections</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">${{ number_format($report_data['loans']['total_outstanding'], 0) }}</div>
                        <div class="status-label">Total Outstanding</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">${{ number_format($report_data['payments']['weekly_overdue'] ?? 0, 0) }}</div>
                        <div class="status-label">Weekly Overdue</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3 class="report-title"> Customer Growth</h3>
                <div class="stats-grid">
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['customers']['new_customers'] }}</div>
                        <div class="status-label">New Customers</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['customers']['active_customers'] }}</div>
                        <div class="status-label">Active Customers</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['loans']['new_accounts'] }}</div>
                        <div class="status-label">New Loan Accounts</div>
                    </div>
                    <div class="status-card">
                        <div class="status-value">{{ $report_data['customers']['total_customers'] }}</div>
                        <div class="status-label">Total Customers</div>
                    </div>
                </div>
            </div>
            @if(count($report_data['loan_capital']) > 0)
                <div class="section">
                    <h3 class="report-title"> Top Loan Products</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Loan Product</th>
                                <th>Applications</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report_data['top_products'] as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->applications }}</td>
                                <td>${{ number_format($product->total_amount, 0) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="footer">
                <p>Weekly report (automated) - Bank Loan System.</p>
                <p>To view detailed analytics, navigate to the admin dashboard</p>
            </div>
        </div>
    </div>
</body>
</html>
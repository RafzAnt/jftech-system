<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Payment History</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/payment_history.css') }}?{{ $paymentHistoryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Payment History</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Review recorded client payments and collection details</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card" style="padding: 1.25rem; --stat-color: var(--success);">
                <div class="stat-value">₱{{ number_format($totalPayments, 2) }}</div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card" style="padding: 1.25rem; --stat-color: var(--accent);">
                <div class="stat-value" style="color: var(--success);">₱{{ number_format($todayPayments, 2) }}</div>
                <div class="stat-label">Today's Collection</div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span class="card-title">Payment History</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                                    <tr>
                                        <th>Client</th>
                                         <th>Bill #</th>
                                         <th>Bill Amount</th>
                                         <th>Paid Amount</th>
                                         <th>Balance</th>
                                         <th>Date</th>
                                         <th>Payment Method</th>
                                         <th>Reference No.</th>
                                         <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                             <strong>{{ htmlspecialchars($payment['client_name']) }}</strong>
                                        </td>
                                        <td>@if ($payment['bill_number']){{ $payment['bill_number'] }}@else<span style="color: var(--text-muted);">—</span>@endif</td>
                                        <td>{{ $payment['bill_amount'] ? '₱' . number_format($payment['bill_amount'], 2) : '-' }}</td>
                                        <td style="color: var(--success); font-weight: 700;">₱{{ number_format($payment['amount'], 2) }}</td>
                                        <td style="color: {{ (float) $payment['balance'] > 0 ? 'var(--warning)' : 'var(--success)' }}; font-weight: 700;">₱{{ number_format(max(0, (float) $payment['balance']), 2) }}</td>
                                        <td>{{ date('M d, Y', strtotime($payment['payment_date'])) }}</td>
                                        <td><span class="badge badge-info">{{ ucfirst($payment['payment_method']) }}</span></td>
                                        <td>{{ $payment['reference_number'] ?: '-' }}</td>
                                        <td>{{ htmlspecialchars($payment['received_by_name'] ?: 'System') }}</td>
                                    </tr>
                                    @endforeach
                                    @if (empty($payments))
                                    <tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">No payment records found</td></tr>
                                    @endif
                                </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

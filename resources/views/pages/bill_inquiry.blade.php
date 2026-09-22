<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Client Records</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/bill_inquiry.css') }}?{{ $billInquiryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Client Records</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">View client records and billing history</p>
            </div>
            <a href="payment_history.php" class="btn btn-success">Record Payment</a>
        </div>

        <!-- Client Selection -->
        @if (!$selectedClient)
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Select Client</span>
            </div>
            <div class="card-body">
                    <form method="GET" action="" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label class="form-label">Search client by name or code</label>
                            <div class="search-box" style="max-width: none;">
                                <input type="text" name="search" class="search-input" placeholder="Search..." value="{{ htmlspecialchars(request()->query('search') ?? '') }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>

                @if (request()->query('search') ?? '')
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Barangay</th>
                                <th>Plan</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($searchResults as $client)
                            <tr>
                                <td><strong>{{ $client['client_code'] }}</strong></td>
                                <td>{{ htmlspecialchars($client['full_name']) }}</td>
                                <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                                <td>{{ $client['wifi_plan'] }}</td>
                                <td><a href="?client_id={{ $client['id'] }}" class="btn btn-sm btn-primary">View Bills</a></td>
                            </tr>
                            @endforeach
                            @if (empty($searchResults))
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No clients found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- Quick Select -->
                <div class="section-title">Quick Select</div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Plan</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allClients as $client)
                            <tr>
                                <td><strong>{{ $client['client_code'] }}</strong></td>
                                <td>{{ htmlspecialchars($client['full_name']) }}</td>
                                <td>{{ htmlspecialchars($client['wifi_plan'] ?? 'N/A') }}</td>
                                <td><a href="?client_id={{ $client['id'] }}" class="btn btn-sm btn-primary">View Bills</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Client Bill Details -->
        @if ($selectedClient)
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header" style="flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <span class="card-title">{{ htmlspecialchars($selectedClient['full_name']) }}</span>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">{{ $selectedClient['client_code'] }} | {{ $selectedClient['barangay_name'] }}</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <span class="badge badge-info">{{ $selectedClient['wifi_plan'] }}</span>
                    <span class="badge badge-success">PHP {{ number_format($selectedClient['monthly_rate'], 2) }}/mo</span>
                </div>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="margin-bottom: 0;">
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem;">PHP {{ number_format($totalDue, 2) }}</div>
                        <div class="stat-label">Outstanding Balance</div>
                    </div>
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--success);">PHP {{ number_format($totalPaid, 2) }}</div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem;">{{ count($bills) }}</div>
                        <div class="stat-label">Open Bills</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bills Table -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span class="card-title">Bills</span>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="bill_inquiry.php" class="btn btn-sm btn-secondary">Change Client</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Period</th>
                                <th>Amount</th>
                                @if ($hasPartialBills)
                                <th>Balance</th>
                                @endif
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bills as $bill)
                            @php($isUpcomingBill = strtotime($bill['billing_period_start']) > strtotime(today()->toDateString()))
                            <tr>
                                <td><strong>{{ $bill['bill_number'] }}</strong></td>
                                <td>{{ date('M d', strtotime($bill['billing_period_start'])) }} - {{ date('M d, Y', strtotime($bill['billing_period_end'])) }}</td>
                                <td style="font-weight: 600;">PHP {{ number_format($bill['amount'], 2) }}</td>
                                @if ($hasPartialBills)
                                <td>
                                    @if ((float) $bill['balance'] > 0 && (float) $bill['balance'] < (float) $bill['amount'])
                                    <span class="badge badge-warning">Balance: PHP {{ number_format($bill['balance'], 2) }}</span>
                                    @else
                                    <span style="color: var(--text-muted);">—</span>
                                    @endif
                                </td>
                                @endif
                                <td>{{ date('M d, Y', strtotime($bill['due_date'])) }}</td>
                                <td>
                                    @if ($isUpcomingBill)
                                        <span class="badge badge-info">Incoming</span>
                                    @elseif ($bill['status'] === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @elseif ($bill['status'] === 'overdue')
                                        <span class="badge badge-danger">Overdue</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst($bill['status']) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="payment_history.php?client_id={{ $selectedClient['id'] }}&bill_id={{ $bill['id'] }}" class="btn btn-sm btn-success">Pay</a>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($bills))
                            <tr><td colspan="{{ $hasPartialBills ? 7 : 6 }}" style="text-align: center; color: var(--text-muted); padding: 2rem;">No billing records found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span class="card-title">Payment History</span>
                <a href="payment_history.php?client_id={{ $selectedClient['id'] }}" class="btn btn-sm btn-primary">View All Payments</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                 <th>Bill #</th>
                                 <th>Bill Amount</th>
                                 <th>Paid Amount</th>
                                 <th>Balance</th>
                                 <th>Method</th>
                                 <th>Reference</th>
                                 <th>Received By</th>
                                 <th>Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($payment['payment_date'])) }}</td>
                                <td>{{ $payment['bill_number'] ?: '<span style="color: var(--text-muted);">-</span>' }}</td>
                                <td>{{ $payment['bill_amount'] ? 'PHP ' . number_format($payment['bill_amount'], 2) : '-' }}</td>
                                <td style="font-weight: 600; color: var(--success);">PHP {{ number_format($payment['amount'], 2) }}</td>
                                <td style="font-weight: 600; color: {{ (float) $payment['balance'] > 0 ? 'var(--warning)' : 'var(--success)' }};">PHP {{ number_format(max(0, (float) $payment['balance']), 2) }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($payment['payment_method']) }}</span></td>
                                <td>{{ $payment['reference_number'] ?: '-' }}</td>
                                <td>{{ htmlspecialchars($payment['received_by_name'] ?: 'System') }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-secondary btn-icon" onclick="printPaymentReceipt({{ $payment['id'] }})" title="Print payment receipt">Print</button>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($payments))
                            <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No payment records found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <script>
    function printPaymentReceipt(paymentId) {
        if (!paymentId) return;
        const url = '{{ url("/pages/print_payment.php") }}?id=' + encodeURIComponent(paymentId) + '&format=thermal';
        window.open(url, '_blank', 'noopener');
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

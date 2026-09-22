<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Record Payment</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/payment_history.css') }}?{{ $paymentHistoryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Record Payment</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Record a payment, then review the complete payment history below</p>
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card" style="padding: 1.25rem; --stat-color: var(--success);">
                <div class="stat-value">PHP {{ number_format($totalPayments, 2) }}</div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card" style="padding: 1.25rem; --stat-color: var(--accent);">
                <div class="stat-value" style="color: var(--success);">PHP {{ number_format($todayPayments, 2) }}</div>
                <div class="stat-label">Today's Collection</div>
            </div>
        </div>

        <!-- New Payment Form -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Record New Payment</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="record_payment" value="1">
                    <input type="hidden" name="submission_token" value="{{ $paymentToken }}">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Client *</label>
                            <select name="client_id" class="form-select" id="paymentClient" required onchange="loadPendingBills(this.value)">
                                <option value="">Select Client</option>
                                @foreach ($clients as $client)
                                <option value="{{ $client['id'] }}" {{ (null !== request()->query('client_id') && request()->query('client_id') == $client['id']) ? 'selected' : '' }}>
                                    {{ $client['client_code'] }} - {{ htmlspecialchars($client['full_name']) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pending Bill *</label>
                            <select name="bill_id" class="form-select" id="billSelect" required onchange="setPaymentBalance(this)">
                                <option value="">Select a pending bill</option>
                                @foreach ($pendingBills as $bill)
                                <option value="{{ $bill['id'] }}" data-balance="{{ $bill['balance'] }}" {{ (null !== request()->query('bill_id') && request()->query('bill_id') == $bill['id']) ? 'selected' : '' }}>
                                    {{ $bill['bill_number'] }} - Balance: PHP {{ number_format($bill['balance'], 2) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (PHP) *</label>
                            <input type="number" name="amount" id="paymentAmount" class="form-input" placeholder="0.00" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" name="payment_date" class="form-input" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reference Number</label>
                            <input type="text" name="reference_number" class="form-input" placeholder="Ref # (optional)">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-input" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </form>
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
                                         <th>Method</th>
                                         <th>Reference</th>
                                         <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                    <tr>
                                        <td>
                                             <strong>{{ $payment['client_code'] }}</strong><br>
                                             <span style="font-size: 0.8rem; color: var(--text-muted);">{{ htmlspecialchars($payment['client_name']) }}</span>
                                        </td>
                                        <td>@if ($payment['bill_number']){{ $payment['bill_number'] }}@else<span style="color: var(--text-muted);">—</span>@endif</td>
                                        <td>{{ $payment['bill_amount'] ? 'PHP ' . number_format($payment['bill_amount'], 2) : '-' }}</td>
                                        <td style="color: var(--success); font-weight: 700;">PHP {{ number_format($payment['amount'], 2) }}</td>
                                        <td style="color: {{ (float) $payment['balance'] > 0 ? 'var(--warning)' : 'var(--success)' }}; font-weight: 700;">PHP {{ number_format(max(0, (float) $payment['balance']), 2) }}</td>
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

    <script>
    // Load pending bills for selected client
    function setPaymentBalance(select) {
        const option = select.options[select.selectedIndex];
        const balance = option?.dataset.balance;
        const amount = document.getElementById('paymentAmount');
        if (!balance || !amount) return;

        amount.value = Number(balance).toFixed(2);
        amount.max = Number(balance).toFixed(2);
    }

    function loadPendingBills(clientId, selectedBillId = null) {
        if (!clientId) return;
        
        fetch('../backend/api/get_pending_bills.php?client_id=' + clientId)
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('billSelect');
                select.innerHTML = '<option value="">Select a pending bill</option>';
                (data.bills || []).forEach(bill => {
                    const option = document.createElement('option');
                    option.value = bill.id;
                    option.dataset.balance = bill.balance;
                    option.textContent = bill.bill_number + ' - Balance: PHP ' + parseFloat(bill.balance).toFixed(2);
                    option.selected = String(bill.id) === String(selectedBillId);
                    select.appendChild(option);
                });
                setPaymentBalance(select);
            })
            .catch(err => console.error('Error loading bills:', err));
    }

    // Load on page load if client pre-selected
    @if (null !== request()->query('client_id') && request()->query('client_id'))
    loadPendingBills({{ request()->query('client_id') }}, {{ request()->query('bill_id') ? (int) request()->query('bill_id') : 'null' }});
    @endif
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

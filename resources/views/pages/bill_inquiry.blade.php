<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Client Accounts</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/bill_inquiry.css') }}?{{ $billInquiryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Client Accounts</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">View client records and billing history</p>
            </div>
        </div>

        @if ($paymentSuccess)
        <div class="alert alert-success">{{ $paymentSuccess }}</div>
        @endif
        @if ($paymentError)
        <div class="alert alert-danger">{{ $paymentError }}</div>
        @endif
        @if ($clientActionSuccess)
        <div class="alert alert-success">{{ $clientActionSuccess }}</div>
        @endif
        @if ($clientActionError)
        <div class="alert alert-danger">{{ $clientActionError }}</div>
        @endif

        <!-- Client Selection -->
        @if (!$selectedClient)
        <div class="tabs-container"><div class="tabs">
            <a href="bill_inquiry.php" class="tab-btn {{ !$showArchived ? 'active' : '' }}">Active Clients</a>
            <a href="bill_inquiry.php?view=archived" class="tab-btn {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
        </div></div>
        <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <span class="card-title">{{ $showOverdue ? 'Clients With Overdue Bills' : 'Select Client' }}</span>
                    @if ($showOverdue)
                    <a href="bill_inquiry.php" class="btn btn-sm btn-secondary">Show All Clients</a>
                    @endif
                </div>
                <div class="card-body">
                    @if ($showOverdue)
                    <div class="alert alert-warning" style="margin-bottom: 1.25rem;">Showing active clients with at least one overdue bill.</div>
                    @endif
                <form method="GET" action="" style="margin-bottom: 1.5rem;">
                    @if ($showArchived)<input type="hidden" name="view" value="archived">@endif
                    @if ($showOverdue)<input type="hidden" name="overdue" value="1">@endif
                    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="min-width: 220px; margin: 0;">
                            <label class="form-label" for="barangayFilter">Barangay</label>
                            <select class="form-select" name="barangay" id="barangayFilter" onchange="this.form.submit()">
                                <option value="">All Barangays</option>
                                @foreach ($barangays as $barangay)
                                <option value="{{ $barangay['id'] }}" {{ $selectedBarangay == $barangay['id'] ? 'selected' : '' }}>
                                    {{ $barangay['barangay_name'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label class="form-label" for="clientSearch">Search client by name</label>
                            <div class="search-box" style="max-width: none;">
                                <input type="search" name="search" id="clientSearch" class="search-input" placeholder="Search..." value="{{ htmlspecialchars(request()->query('search') ?? '') }}">
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
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Barangay</th>
                                <th>Mobile Number</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th aria-label="Client actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($searchResults as $client)
                            <tr>
                                <td>{{ htmlspecialchars($client['full_name']) }}</td>
                                <td>{{ $client['branch_name'] ?: 'N/A' }}</td>
                                <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                                <td>{{ $client['contact_number'] ?: '--' }}</td>
                                <td>{{ $client['email'] ?: '--' }}</td>
                                <td>{{ $client['wifi_plan'] }}</td>
                                <td>@include('pages.partials.client_status_badge', ['status' => $client['status']])</td>
                                <td>@include('pages.partials.client_record_actions', ['client' => $client, 'showArchived' => $showArchived])</td>
                            </tr>
                            @endforeach
                            @if (empty($searchResults))
                            <tr><td colspan="8" style="text-align: center; color: var(--text-muted);">No clients found</td></tr>
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
                                <th>Name</th>
                                <th>Mobile Number</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th aria-label="Client actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allClients as $client)
                            <tr>
                                <td>{{ htmlspecialchars($client['full_name']) }}</td>
                                <td>{{ $client['contact_number'] ?: '--' }}</td>
                                <td>{{ $client['email'] ?: '--' }}</td>
                                <td>{{ htmlspecialchars($client['wifi_plan'] ?? 'N/A') }}</td>
                                <td>@include('pages.partials.client_status_badge', ['status' => $client['status']])</td>
                                <td>@include('pages.partials.client_record_actions', ['client' => $client, 'showArchived' => $showArchived])</td>
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
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">{{ $selectedClient['branch_name'] ?: 'N/A' }} · {{ $selectedClient['barangay_name'] ?: 'N/A' }}</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <span class="badge badge-info">{{ $selectedClient['wifi_plan'] }}</span>
                    <span class="badge badge-success">₱{{ number_format($selectedClient['monthly_rate'], 2) }}/mo</span>
                </div>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="margin-bottom: 0;">
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem;">₱{{ number_format($totalDue, 2) }}</div>
                        <div class="stat-label">Outstanding Balance</div>
                    </div>
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--success);">₱{{ number_format($totalPaid, 2) }}</div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem;">{{ $openBillsCount }}</div>
                        <div class="stat-label">Open Bills</div>
                    </div>
                    <div class="stat-card" style="padding: 1rem;">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--accent);">{{ $incomingBillsCount }}</div>
                        <div class="stat-label">Incoming Bills</div>
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
                                <th>Billing Period</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th aria-label="Bill actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bills as $bill)
                            @php($isUpcomingBill = strtotime($bill['billing_period_start']) > strtotime(today()->toDateString()))
                            <tr>
                                <td><strong>{{ $bill['bill_number'] }}</strong></td>
                                <td>{{ date('M d', strtotime($bill['billing_period_start'])) }} - {{ date('M d, Y', strtotime($bill['billing_period_end'])) }}</td>
                                <td style="font-weight: 600;">₱{{ number_format($bill['amount'], 2) }}</td>
                                <td>
                                    @if ((float) $bill['balance'] > 0)
                                    <span class="badge {{ (float) $bill['balance'] < (float) $bill['amount'] ? 'badge-warning' : 'badge-danger' }}">₱{{ number_format($bill['balance'], 2) }}</span>
                                    @else
                                    <span style="color: var(--text-muted);">--</span>
                                    @endif
                                </td>
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
                                    <button type="button" class="btn btn-sm btn-success" data-bill-id="{{ $bill['id'] }}" data-bill-number="{{ $bill['bill_number'] }}" data-balance="{{ $bill['balance'] }}" onclick="openPaymentModal(this)">Pay</button>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($bills))
                            <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No billing records found</td></tr>
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
                                 <th>Payment Method</th>
                                 <th>Reference No.</th>
                                 <th>Received By</th>
                                 <th>Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                            <tr>
                                <td>{{ date('M d, Y', strtotime($payment['payment_date'])) }}</td>
                                <td>
                                    @if ($payment['bill_number'])
                                        <strong>{{ $payment['bill_number'] }}</strong>
                                    @else
                                        <span style="color: var(--text-muted);">Unlinked legacy payment</span>
                                    @endif
                                </td>
                                <td>{{ $payment['bill_amount'] ? '₱' . number_format($payment['bill_amount'], 2) : '-' }}</td>
                                <td style="font-weight: 600; color: var(--success);">₱{{ number_format($payment['amount'], 2) }}</td>
                                <td style="font-weight: 600; color: {{ (float) $payment['balance'] > 0 ? 'var(--warning)' : 'var(--text-muted)' }};">
                                    @if ((float) $payment['balance'] > 0)
                                    ₱{{ number_format((float) $payment['balance'], 2) }}
                                    @else
                                    --
                                    @endif
                                </td>
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

        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header"><span class="card-title">Plan Change History</span></div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container"><table class="data-table">
                    <thead><tr><th>Date</th><th>Previous Plan</th><th>New Plan</th><th>Reason</th><th>Changed By</th></tr></thead>
                    <tbody>
                    @forelse ($planChangeHistory as $change)
                    <tr>
                        <td>{{ date('M d, Y h:i A', strtotime($change['created_at'])) }}</td>
                        <td>{{ $change['previous_plan_name'] ?: 'No assigned plan' }}<br><small style="color:var(--text-muted)">₱{{ number_format($change['previous_monthly_rate'], 2) }}/mo</small></td>
                        <td><strong>{{ $change['new_plan_name'] }}</strong><br><small style="color:var(--success)">₱{{ number_format($change['new_monthly_rate'], 2) }}/mo</small></td>
                        <td>{{ $change['reason'] ?: '—' }}</td>
                        <td>{{ $change['changed_by_name'] ?: 'System' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem;">No plan changes recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        @endif

        @if ($selectedClient)
        <div class="modal-overlay" id="paymentModal" onclick="if (event.target === this) closePaymentModal()">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3 class="modal-title">Record New Payment</h3>
                    <button type="button" class="modal-close" onclick="closePaymentModal()">&times;</button>
                </div>
                <form method="POST" action="?client_id={{ $selectedClient['id'] }}">
                    @csrf
                    <input type="hidden" name="record_payment" value="1">
                    <input type="hidden" name="submission_token" value="{{ $paymentToken }}">
                    <input type="hidden" name="client_id" value="{{ $selectedClient['id'] }}">
                    <input type="hidden" name="bill_id" id="paymentBillId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Client</label>
                            <input class="form-input" type="text" value="{{ $selectedClient['full_name'] }}" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pending Bill</label>
                            <input class="form-input" type="text" id="paymentBillLabel" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="paymentAmount">Amount (₱) *</label>
                            <input class="form-input" type="number" name="amount" id="paymentAmount" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="paymentDate">Payment Date *</label>
                            <input class="form-input" type="date" name="payment_date" id="paymentDate" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="paymentMethod">Payment Method</label>
                            <select class="form-select" name="payment_method" id="paymentMethod"><option value="cash">Cash</option><option value="gcash">GCash</option></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="paymentReference">Reference No.</label>
                            <input class="form-input" type="text" name="reference_number" id="paymentReference" placeholder="Ref # (optional)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="paymentNotes">Notes</label>
                        <textarea class="form-input" name="notes" id="paymentNotes" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                        <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
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

    function openPaymentModal(button) {
        const balance = Number(button.dataset.balance || 0);
        document.getElementById('paymentBillId').value = button.dataset.billId;
        document.getElementById('paymentBillLabel').value = `${button.dataset.billNumber} — Balance: ₱${balance.toFixed(2)}`;
        const amount = document.getElementById('paymentAmount');
        amount.value = balance.toFixed(2);
        amount.max = balance.toFixed(2);
        document.getElementById('paymentModal').classList.add('active');
        amount.focus();
    }

    function closePaymentModal() {
        document.getElementById('paymentModal')?.classList.remove('active');
    }

    @if ($paymentError && $selectedClient)
    document.addEventListener('DOMContentLoaded', () => document.getElementById('paymentModal').classList.add('active'));
    @endif
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

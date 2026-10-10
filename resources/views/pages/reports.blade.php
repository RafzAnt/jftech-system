<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Reports</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?{{ $reportsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    @include('layouts.sidebar')
    <main class="main-content">
        <div class="top-bar report-heading">
            <div><p class="report-eyebrow">Management workspace</p><h1 class="page-title">System Reports</h1><p>Financial, client, workforce, and inventory performance in one report.</p></div>
            <form method="GET" action="{{ url('/pages/reports.php') }}" class="report-filter no-print">
                <div><label for="month">Report month</label><input id="month" type="month" name="month" value="{{ $month }}"></div>
                <span class="filter-or">or</span>
                <div><label for="from">From date</label><input id="from" type="date" name="from" value="{{ $periodStart }}"></div>
                <div><label for="to">To date</label><input id="to" type="date" name="to" value="{{ $periodEnd }}"></div>
                <button class="btn btn-primary" type="submit">View report</button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_monthly_reports.php?from=' . $periodStart . '&to=' . $periodEnd) }}">Print</a>
            </form>
        </div>
        <section class="report-period"><strong>{{ date('M d, Y', strtotime($periodStart)) }} – {{ date('M d, Y', strtotime($periodEnd)) }}</strong><span>System-generated management report · Updated {{ now()->format('M d, Y h:i A') }}</span></section>
        <section class="report-metrics">
            <article class="report-metric accent"><span>Collections received</span><strong>₱{{ number_format($metrics['collections'], 2) }}</strong><small>{{ number_format($metrics['paymentCount']) }} payment(s) recorded</small></article>
            <article class="report-metric info"><span>Billed in period</span><strong>₱{{ number_format($metrics['billedAmount'], 2) }}</strong><small>{{ number_format($bills->count()) }} bill(s) issued</small></article>
            <article class="report-metric success"><span>Bill settlement rate</span><strong>{{ number_format($metrics['settlementRate'], 1) }}%</strong><small>based on bills issued in this period</small></article>
            <article class="report-metric warning"><span>Outstanding balance</span><strong>₱{{ number_format($metrics['outstanding'], 2) }}</strong><small>{{ number_format($metrics['openBills']) }} pending or overdue bill(s)</small></article>
        </section>
        <section class="report-grid">
            <article class="card report-card"><div class="card-header"><span class="card-title">Payments Received</span><span class="badge badge-success">{{ number_format($metrics['paymentCount']) }} in period</span></div><div class="data-table-container"><table class="data-table"><thead><tr><th>Date</th><th>Client</th><th>Method</th><th>Reference</th><th class="amount">Amount</th></tr></thead><tbody>@forelse ($payments as $payment)<tr><td>{{ date('M d, Y', strtotime($payment->payment_date)) }}</td><td>{{ $payment->full_name }}</td><td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td class="amount">₱{{ number_format($payment->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="report-empty">No payments recorded for this period.</td></tr>@endforelse</tbody></table></div></article>
            <article class="card report-card"><div class="card-header"><span class="card-title">Bills for Selected Period</span><span class="badge badge-warning">{{ number_format($metrics['openBills']) }} open</span></div><div class="data-table-container"><table class="data-table"><thead><tr><th>Due date</th><th>Client</th><th>Bill no.</th><th>Status</th><th class="amount">Amount</th></tr></thead><tbody>@forelse ($bills as $bill)<tr><td>{{ date('M d, Y', strtotime($bill->due_date)) }}</td><td>{{ $bill->full_name }}</td><td>{{ $bill->bill_number }}</td><td>@include('components.status-badge', ['status' => $bill->status])</td><td class="amount">₱{{ number_format($bill->amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="report-empty">No bills found for this period.</td></tr>@endforelse</tbody></table></div></article>
        </section>
        <section class="report-grid report-grid-bottom">
            <article class="card report-card"><div class="card-header"><span class="card-title">Client Status</span></div><div class="status-summary">@forelse ($clientStatus as $status => $total)<div><strong>{{ number_format($total) }}</strong><span>{{ ucfirst($status) }}</span></div>@empty<p class="report-empty">No clients registered yet.</p>@endforelse</div></article>
            <article class="card report-card"><div class="card-header"><span class="card-title">Low Stock Alert</span></div><div class="data-table-container"><table class="data-table"><thead><tr><th>Item</th><th>Category</th><th>In stock</th><th>Reorder at</th></tr></thead><tbody>@forelse ($lowStockItems as $item)<tr><td>{{ $item->item_name }}</td><td>{{ $item->category }}</td><td class="low-stock-number">{{ number_format($item->quantity) }}</td><td>{{ number_format($item->reorder_level) }}</td></tr>@empty<tr><td colspan="4" class="report-empty">All active inventory items are above their reorder levels.</td></tr>@endforelse</tbody></table></div></article>
        </section>
        <section class="report-grid report-grid-bottom">
            <article class="card report-card"><div class="card-header"><span class="card-title">Workforce Attendance</span><span class="badge badge-info">Selected period</span></div><div class="status-summary"><div><strong>{{ number_format($attendanceSummary->records ?? 0) }}</strong><span>records</span></div><div><strong>{{ number_format($attendanceSummary->present ?? 0) }}</strong><span>present</span></div><div><strong>{{ number_format($attendanceSummary->late ?? 0) }}</strong><span>late</span></div><div><strong>{{ number_format($attendanceSummary->absent ?? 0) }}</strong><span>absent</span></div></div></article>
            <article class="card report-card"><div class="card-header"><span class="card-title">System Snapshot</span><span class="badge badge-success">Current</span></div><div class="status-summary"><div><strong>{{ number_format($metrics['activeClients']) }}</strong><span>active clients</span></div><div><strong>{{ number_format($systemSnapshot['activeEmployees']) }}</strong><span>active employees</span></div><div><strong>{{ number_format($systemSnapshot['activeUsers']) }}</strong><span>active users</span></div><div><strong>₱{{ number_format($systemSnapshot['inventoryValue'], 2) }}</strong><span>inventory value</span></div></div></article>
        </section>
    </main>
    <script>
        document.getElementById('month')?.addEventListener('change', function () {
            if (!this.value) return;
            const [year, month] = this.value.split('-');
            document.getElementById('from').value = `${year}-${month}-01`;
            document.getElementById('to').value = `${year}-${month}-${new Date(year, month, 0).getDate().toString().padStart(2, '0')}`;
        });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body></html>

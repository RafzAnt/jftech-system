<!DOCTYPE html>
<html lang="en-PH">
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
            <div><p class="report-eyebrow">Business overview</p><h1 class="page-title">Business Reports</h1><p>A clear summary of payments, bills, clients, staff, and inventory.</p></div>
            <form method="GET" action="{{ url('/pages/reports.php') }}" class="report-filter no-print">
                <div><label for="month">Report Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div>
                <span class="filter-or">or</span>
                <div><label for="from">Start Date</label><input id="from" type="date" name="from" value="{{ $periodStart }}"></div>
                <div><label for="to">End Date</label><input id="to" type="date" name="to" value="{{ $periodEnd }}"></div>
                <button class="btn btn-primary" type="submit">View report</button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_monthly_reports.php?from=' . $periodStart . '&to=' . $periodEnd) }}">Print</a>
            </form>
        </div>
        <section class="report-period"><strong>{{ date('M d, Y', strtotime($periodStart)) }} – {{ date('M d, Y', strtotime($periodEnd)) }}</strong><span>Business report · Updated {{ now()->format('M d, Y h:i A') }}</span></section>
        <section class="report-metrics">
            <article class="report-metric accent"><span>Total payments received</span><strong>₱{{ number_format($metrics['collections'], 2) }}</strong><small>{{ number_format($metrics['paymentCount']) }} payment(s) in selected period</small></article>
            <article class="report-metric info"><span>Total bills</span><strong>₱{{ number_format($metrics['totalBillsAmount'], 2) }}</strong><small>{{ number_format($metrics['totalBills']) }} bill(s) issued in selected period</small></article>
            <article class="report-metric success"><span>Bill payment rate</span><strong>{{ number_format($metrics['settlementRate'], 1) }}%</strong><small>based on bills issued in this period</small></article>
            <article class="report-metric warning"><span>Outstanding balance</span><strong>₱{{ number_format($metrics['outstanding'], 2) }}</strong><small>{{ number_format($metrics['openBills']) }} pending or overdue bill(s)</small></article>
        </section>
        <section class="report-summary-grid">
            <article class="card report-card"><div class="card-header"><span class="card-title">Client Summary</span><span class="badge badge-info">Current</span></div><div class="status-summary">@forelse ($clientStatus as $status => $total)<div><strong>{{ number_format($total) }}</strong><span>{{ ucfirst($status) }}</span></div>@empty<p class="report-empty">No clients registered yet.</p>@endforelse</div></article>
            <article class="card report-card"><div class="card-header"><span class="card-title">Employee Attendance</span><span class="badge badge-info">Selected period</span></div><div class="status-summary"><div><strong>{{ number_format($attendanceSummary->records ?? 0) }}</strong><span>records</span></div><div><strong>{{ number_format($attendanceSummary->present ?? 0) }}</strong><span>present</span></div><div><strong>{{ number_format($attendanceSummary->late ?? 0) }}</strong><span>late</span></div><div><strong>{{ number_format($attendanceSummary->absent ?? 0) }}</strong><span>absent</span></div></div></article>
            <article class="card report-card"><div class="card-header"><span class="card-title">Business Summary</span><span class="badge badge-success">Current</span></div><div class="status-summary"><div><strong>{{ number_format($systemSnapshot['activeEmployees']) }}</strong><span>active employees</span></div><div><strong>{{ number_format($systemSnapshot['activeUsers']) }}</strong><span>active users</span></div><div><strong>{{ number_format($metrics['lowStock']) }}</strong><span>low stock items</span></div><div><strong>₱{{ number_format($systemSnapshot['inventoryValue'], 2) }}</strong><span>inventory value</span></div></div></article>
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

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
            <div><p class="report-eyebrow">Summary report</p><h1 class="page-title">Business Report</h1><p>Totals for the selected period only.</p></div>
            <form method="GET" action="{{ url('/pages/reports.php') }}" class="report-filter no-print" id="reportFilter">
                <div><label for="month">Report Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div>
                <span class="filter-or">or</span>
                <div><label for="from">Start Date</label><input id="from" type="date" name="from" value="{{ $periodStart }}"></div>
                <div><label for="to">End Date</label><input id="to" type="date" name="to" value="{{ $periodEnd }}"></div>
                <button class="btn btn-primary" type="submit">View report</button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_monthly_reports.php?from=' . $periodStart . '&to=' . $periodEnd) }}">Print</a>
            </form>
        </div>

        <section class="report-period">
            <div><span>Report period</span><strong>{{ $report['label'] }}</strong></div>
            <p>{{ date('M d, Y', strtotime($periodStart)) }} to {{ date('M d, Y', strtotime($periodEnd)) }} &middot; Generated {{ now()->format('M d, Y h:i A') }}</p>
        </section>

        @php($b = $report['bills'])
        <h2 class="report-section-title">Billing</h2>
        <section class="stats-grid report-stats">
            <article class="stat-card">
                <div class="stat-label">Total bills for this period</div>
                <div class="stat-value">₱{{ number_format($b['amount'], 2) }}</div>
                <div class="stat-change stat-change--muted"><span>{{ number_format($b['count']) }} bill(s) issued to {{ number_format($b['billedClients']) }} client(s)</span></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Paid bills</div>
                <div class="stat-value">₱{{ number_format($b['paidAmount'], 2) }}</div>
                <div class="stat-change positive"><span>{{ number_format($b['paidCount']) }} bill(s) &middot; {{ number_format($b['paidRate'], 1) }}% of total</span></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Pending bills</div>
                <div class="stat-value">₱{{ number_format($b['pendingAmount'], 2) }}</div>
                <div class="stat-change stat-change--muted"><span>{{ number_format($b['pendingCount']) }} bill(s) awaiting payment</span></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Overdue bills</div>
                <div class="stat-value">₱{{ number_format($b['overdueAmount'], 2) }}</div>
                <div class="stat-change {{ $b['overdueCount'] > 0 ? 'negative' : 'positive' }}"><span>{{ number_format($b['overdueCount']) }} bill(s) &middot; {{ number_format($b['overdueClients']) }} client(s)</span></div>
            </article>
        </section>
        <div class="report-progress" role="img" aria-label="{{ number_format($b['paidRate'], 1) }}% of billed amount is paid">
            <div class="report-progress__head"><span>Billed amount settled</span><strong>{{ number_format($b['paidRate'], 1) }}%</strong></div>
            <div class="report-progress__bar"><span style="width: {{ min(100, max(0, $b['paidRate'])) }}%"></span></div>
            <p>Outstanding balance after partial payments: <strong>₱{{ number_format($b['outstanding'], 2) }}</strong>@if ($b['cancelledCount'] > 0) &middot; {{ number_format($b['cancelledCount']) }} cancelled bill(s) not counted @endif</p>
        </div>

        <h2 class="report-section-title">Payments received</h2>
        <section class="report-summary-grid report-summary-grid--two">
            <article class="card report-card">
                <div class="card-header"><span class="card-title">Collections</span><span class="badge badge-info">{{ $report['label'] }}</span></div>
                <dl class="report-list">
                    <div><dt>Total payments received</dt><dd>₱{{ number_format($report['payments']['collected'], 2) }}</dd></div>
                    <div><dt>Number of payments</dt><dd>{{ number_format($report['payments']['count']) }}</dd></div>
                </dl>
            </article>
            <article class="card report-card">
                <div class="card-header"><span class="card-title">By payment method</span></div>
                <dl class="report-list">
                    @forelse ($report['payments']['methods'] as $method)
                    <div><dt>{{ ucwords(str_replace('_', ' ', $method->payment_method)) }} <small>({{ number_format($method->payment_count) }})</small></dt><dd>₱{{ number_format($method->total, 2) }}</dd></div>
                    @empty
                    <p class="report-empty">No payments received in this period.</p>
                    @endforelse
                </dl>
            </article>
        </section>

        <h2 class="report-section-title">Clients, staff and inventory</h2>
        <section class="report-summary-grid">
            <article class="card report-card">
                <div class="card-header"><span class="card-title">Clients</span></div>
                <dl class="report-list">
                    <div><dt>New installations</dt><dd>{{ number_format($report['clients']['new']) }}</dd></div>
                    <div><dt>Clients billed</dt><dd>{{ number_format($b['billedClients']) }}</dd></div>
                    <div><dt>Clients with overdue bills</dt><dd>{{ number_format($b['overdueClients']) }}</dd></div>
                </dl>
            </article>
            <article class="card report-card">
                <div class="card-header"><span class="card-title">Attendance &amp; payroll</span></div>
                <dl class="report-list">
                    <div><dt>Attendance records</dt><dd>{{ number_format($report['attendance']['records']) }}</dd></div>
                    <div><dt>Present / Late / Absent</dt><dd>{{ number_format($report['attendance']['present']) }} / {{ number_format($report['attendance']['late']) }} / {{ number_format($report['attendance']['absent']) }}</dd></div>
                    <div><dt>Payroll entries</dt><dd>{{ number_format($report['payroll']['entries']) }}</dd></div>
                    <div><dt>Net pay released</dt><dd>₱{{ number_format($report['payroll']['net'], 2) }}</dd></div>
                </dl>
            </article>
            <article class="card report-card">
                <div class="card-header"><span class="card-title">Inventory requests</span></div>
                <dl class="report-list">
                    <div><dt>Total requests</dt><dd>{{ number_format($report['requests']['total']) }}</dd></div>
                    <div><dt>Approved</dt><dd>{{ number_format($report['requests']['approved']) }}</dd></div>
                    <div><dt>Pending</dt><dd>{{ number_format($report['requests']['pending']) }}</dd></div>
                    <div><dt>Denied</dt><dd>{{ number_format($report['requests']['denied']) }}</dd></div>
                </dl>
            </article>
        </section>
    </main>
    <script>
        // Picking a month fills the dates and shows that month straight away.
        document.getElementById('month')?.addEventListener('change', function () {
            if (!this.value) return;
            const [year, month] = this.value.split('-');
            document.getElementById('from').value = `${year}-${month}-01`;
            document.getElementById('to').value = `${year}-${month}-${new Date(year, month, 0).getDate().toString().padStart(2, '0')}`;
            document.getElementById('reportFilter').submit();
        });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body></html>

<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Reports</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
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
            <div><p class="report-eyebrow">Business report</p><h1 class="page-title">Business Report</h1><p>Every record for the selected period, with totals. Showing: <strong>{{ $branchLabel }}</strong></p></div>
            <form method="GET" action="{{ url('/pages/reports.php') }}" class="report-filter no-print" id="reportFilter">
                <div><label for="month">Report Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div>
                <span class="filter-or">or</span>
                <div><label for="from">Start Date</label><input id="from" type="date" name="from" value="{{ $periodStart }}"></div>
                <div><label for="to">End Date</label><input id="to" type="date" name="to" value="{{ $periodEnd }}"></div>
                @if (auth()->user()?->isSuperAdmin())
                <div><label for="branch">Branch</label>
                    <select id="branch" name="branch">
                        <option value="">All branches</option>
                        @foreach ($reportBranches as $reportBranch)
                        <option value="{{ $reportBranch }}" {{ $branch === $reportBranch ? 'selected' : '' }}>{{ $reportBranch }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <button class="btn btn-primary" type="submit">View report</button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ url('/pages/print_monthly_reports.php?from=' . $periodStart . '&to=' . $periodEnd . ($branch !== null ? '&branch=' . urlencode($branch) : '')) }}">Print</a>
            </form>
        </div>

        <section class="report-period">
            <div><span>Report period</span><strong>{{ $report['label'] }}</strong></div>
            <p>{{ date('M d, Y', strtotime($periodStart)) }} to {{ date('M d, Y', strtotime($periodEnd)) }} &middot; Generated {{ now()->format('M d, Y h:i A') }}</p>
        </section>

        @php($b = $report['bills'])
        @php($d = $report['details'])
        @php($peso = fn ($amount) => '₱' . number_format((float) $amount, 2))
        @php($statusBadge = ['paid' => 'badge-success', 'approved' => 'badge-success', 'pending' => 'badge-warning', 'processed' => 'badge-info', 'overdue' => 'badge-danger', 'denied' => 'badge-danger', 'draft' => 'badge-secondary'])
        <h2 class="report-section-title">Summary</h2>
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

        <h2 class="report-section-title">1. Billing</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Bills for {{ $report['label'] }}</span><span class="badge badge-info">{{ number_format(count($d['bills']['rows'])) }} bill(s)</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Bill No.</th><th>Client</th><th>Billing period</th><th>Due date</th><th>Status</th><th class="num">Amount</th><th class="num">Received</th><th class="num">Balance</th></tr></thead>
                    <tbody>
                        @forelse ($d['bills']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $row['bill_number'] }}</td>
                            <td><strong>{{ $row['client_name'] }}</strong> <small class="report-muted">{{ $row['client_code'] }}</small></td>
                            <td>{{ date('M d', strtotime($row['billing_period_start'])) }} &ndash; {{ date('M d, Y', strtotime($row['billing_period_end'])) }}</td>
                            <td>{{ date('M d, Y', strtotime($row['due_date'])) }}</td>
                            <td><span class="badge {{ $statusBadge[$row['status']] ?? 'badge-secondary' }}">{{ ucfirst($row['status']) }}</span></td>
                            <td class="num">{{ $peso($row['amount']) }}</td>
                            <td class="num">{{ $peso($row['received']) }}</td>
                            <td class="num">{{ $peso($row['balance']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="report-empty-row">No bills in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="report-total-row"><td colspan="6">Total ({{ number_format(count($d['bills']['rows'])) }} bill(s))</td><td class="num">{{ $peso($d['bills']['total']['amount']) }}</td><td class="num">{{ $peso($d['bills']['total']['received']) }}</td><td class="num">{{ $peso($d['bills']['total']['balance']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        <h2 class="report-section-title">2. Payments received</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Payments by method</span><span class="badge badge-info">{{ $report['label'] }}</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>Payment method</th><th class="num">Payments</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($report['payments']['methods'] as $method)
                        <tr><td>{{ ucwords(str_replace('_', ' ', $method->payment_method)) }}</td><td class="num">{{ number_format($method->payment_count) }}</td><td class="num">{{ $peso($method->total) }}</td></tr>
                        @empty
                        <tr><td colspan="3" class="report-empty-row">No payments received in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="report-total-row"><td>Total payments received</td><td class="num">{{ number_format($report['payments']['count']) }}</td><td class="num">{{ $peso($report['payments']['collected']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Payment list</span><span class="badge badge-info">{{ number_format(count($d['payments']['rows'])) }} payment(s)</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Date</th><th>Client</th><th>Bill No.</th><th>Method</th><th>Reference No.</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($d['payments']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ date('M d, Y', strtotime($row['payment_date'])) }}</td>
                            <td><strong>{{ $row['client_name'] }}</strong> <small class="report-muted">{{ $row['client_code'] }}</small></td>
                            <td>{{ $row['bill_number'] ?: '—' }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $row['payment_method'])) }}</td>
                            <td>{{ $row['reference_number'] ?: '—' }}</td>
                            <td class="num">{{ $peso($row['amount']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="report-empty-row">No payments received in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="report-total-row"><td colspan="6">Total ({{ number_format(count($d['payments']['rows'])) }} payment(s))</td><td class="num">{{ $peso($d['payments']['total']['amount']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        <h2 class="report-section-title">3. New installations</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Clients installed in {{ $report['label'] }}</span><span class="badge badge-info">{{ number_format(count($d['clients']['rows'])) }} client(s)</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Installed</th><th>Client</th><th>Branch</th><th>Barangay</th><th>Plan</th><th class="num">Monthly rate</th></tr></thead>
                    <tbody>
                        @forelse ($d['clients']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ date('M d, Y', strtotime($row['installation_date'])) }}</td>
                            <td><strong>{{ $row['full_name'] }}</strong> <small class="report-muted">{{ $row['client_code'] }}</small></td>
                            <td>{{ $row['branch_name'] ?: '—' }}</td>
                            <td>{{ $row['registered_barangay_name'] ?: '—' }}</td>
                            <td>{{ $row['wifi_plan'] ?: '—' }}</td>
                            <td class="num">{{ $peso($row['monthly_rate']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="report-empty-row">No new installations in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="report-total-row"><td colspan="6">Total ({{ number_format(count($d['clients']['rows'])) }} client(s))</td><td class="num">{{ $peso($d['clients']['total']['monthly']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        <h2 class="report-section-title">4. Attendance</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Attendance per employee</span><span class="badge badge-info">{{ number_format(count($d['attendance']['rows'])) }} employee(s)</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Employee</th><th>Position</th><th class="num">Records</th><th class="num">Present</th><th class="num">Late</th><th class="num">Absent</th><th class="num">Hours</th></tr></thead>
                    <tbody>
                        @forelse ($d['attendance']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $row['full_name'] }}</strong> <small class="report-muted">{{ $row['employee_code'] }}</small></td>
                            <td>{{ $row['position'] ?: '—' }}</td>
                            <td class="num">{{ number_format($row['records']) }}</td>
                            <td class="num">{{ number_format($row['present']) }}</td>
                            <td class="num">{{ number_format($row['late']) }}</td>
                            <td class="num">{{ number_format($row['absent']) }}</td>
                            <td class="num">{{ number_format($row['hours'], 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="report-empty-row">No attendance recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @php($t = $d['attendance']['total'])
                    <tfoot><tr class="report-total-row"><td colspan="3">Total</td><td class="num">{{ number_format($t['records']) }}</td><td class="num">{{ number_format($t['present']) }}</td><td class="num">{{ number_format($t['late']) }}</td><td class="num">{{ number_format($t['absent']) }}</td><td class="num">{{ number_format($t['hours'], 2) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        <h2 class="report-section-title">5. Payroll</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Payroll released in {{ $report['label'] }}</span><span class="badge badge-info">{{ number_format(count($d['payroll']['rows'])) }} entr{{ count($d['payroll']['rows']) === 1 ? 'y' : 'ies' }}</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Employee</th><th>Pay period</th><th>Status</th><th class="num">Days</th><th class="num">Gross pay</th><th class="num">Deductions</th><th class="num">Net pay</th></tr></thead>
                    <tbody>
                        @forelse ($d['payroll']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $row['full_name'] }}</strong> <small class="report-muted">{{ $row['position'] ?: $row['employee_code'] }}</small></td>
                            <td>{{ date('M d', strtotime($row['payroll_period_start'])) }} &ndash; {{ date('M d, Y', strtotime($row['payroll_period_end'])) }}</td>
                            <td><span class="badge {{ $statusBadge[$row['status']] ?? 'badge-secondary' }}">{{ ucfirst($row['status']) }}</span></td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $row['total_days_worked'], 2), '0'), '.') }}</td>
                            <td class="num">{{ $peso($row['gross_pay']) }}</td>
                            <td class="num">{{ $peso($row['deductions']) }}</td>
                            <td class="num">{{ $peso($row['net_pay']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="report-empty-row">No payroll entries in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @php($t = $d['payroll']['total'])
                    <tfoot><tr class="report-total-row"><td colspan="4">Total</td><td class="num">{{ rtrim(rtrim(number_format($t['days'], 2), '0'), '.') }}</td><td class="num">{{ $peso($t['gross']) }}</td><td class="num">{{ $peso($t['deductions']) }}</td><td class="num">{{ $peso($t['net']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        @if ($d['requests'] !== null)
        <h2 class="report-section-title">6. Inventory requests</h2>
        <div class="card report-table-card">
            <div class="card-header"><span class="card-title">Stock requests in {{ $report['label'] }}</span><span class="badge badge-info">{{ number_format($report['requests']['total']) }} request(s) &middot; {{ number_format($report['requests']['approved']) }} approved &middot; {{ number_format($report['requests']['pending']) }} pending &middot; {{ number_format($report['requests']['denied']) }} denied</span></div>
            <div class="data-table-container">
                <table class="data-table report-table">
                    <thead><tr><th>#</th><th>Date</th><th>Requested by</th><th>Item</th><th>Status</th><th class="num">Quantity</th></tr></thead>
                    <tbody>
                        @forelse ($d['requests']['rows'] as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ date('M d, Y', strtotime($row['created_at'])) }}</td>
                            <td>{{ $row['employee_name'] }}</td>
                            <td>{{ $row['item_name'] }}</td>
                            <td><span class="badge {{ $statusBadge[$row['status']] ?? 'badge-secondary' }}">{{ ucfirst($row['status']) }}</span></td>
                            <td class="num">{{ number_format($row['quantity']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="report-empty-row">No inventory requests in this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="report-total-row"><td colspan="5">Total ({{ number_format(count($d['requests']['rows'])) }} request(s))</td><td class="num">{{ number_format($d['requests']['total']['quantity']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        @endif
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

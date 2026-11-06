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
            <div><p class="report-eyebrow">Reports</p><h1 class="page-title">Business Report</h1><p>{{ $periodLabel }} &middot; Showing: <strong>{{ $branchLabel }}</strong></p></div>
        </div>

        {{-- The summary as plain tables on the screen. The standard report format (letterhead, signatures) is only the Print report button. --}}
        <section class="card report-print-card">
            <form method="GET" action="{{ url('/pages/reports.php') }}" class="report-filter" id="reportFilter">
                <div><label for="month">Report Month</label><input id="month" type="month" name="month" value="{{ $month }}"></div>
                <span class="filter-or">or</span>
                <div><label for="from">Start Date</label><input id="from" type="date" name="from" value="{{ $periodStart }}" required></div>
                <div><label for="to">End Date</label><input id="to" type="date" name="to" value="{{ $periodEnd }}" required></div>
                @if (auth()->user()?->isSuperAdmin())
                <div class="report-branch"><label for="branch">Branch</label>
                    {{-- Type a branch and pick it from the suggestions; left empty it is every branch. --}}
                    <div class="location-field">
                        <input id="branch" type="text" name="branch" value="{{ $branch }}" placeholder="All branches" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="branchSuggestions" aria-expanded="false">
                        <div id="branchSuggestions" class="location-suggestions" role="listbox" hidden></div>
                    </div>
                </div>
                @else
                <input type="hidden" name="branch" value="{{ $branch }}">
                @endif
                <button class="btn btn-primary" type="submit">View report</button>
                {{-- Same dates and branch as the form, but the standard report format, in a new tab. --}}
                <button class="btn btn-secondary" type="submit" formaction="{{ url('/pages/print_monthly_reports.php') }}" formtarget="_blank" formnovalidate>Print report</button>
            </form>
        </section>

        @php
            $b = $report['bills'];
            $pay = $report['payments'];
            $att = $report['attendance'];
            $payroll = $report['payroll'];
            $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
            $share = fn ($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';
            $hours = rtrim(rtrim(number_format($att['hours'], 2), '0'), '.');
        @endphp

        <section class="report-metrics" aria-label="Report summary">
            <article class="card report-metric">
                <span>Total billed</span>
                <strong>{{ $peso($b['amount']) }}</strong>
                <small>{{ number_format($b['count']) }} bill{{ $b['count'] === 1 ? '' : 's' }} for this period</small>
            </article>
            <article class="card report-metric success">
                <span>Payments received</span>
                <strong>{{ $peso($pay['collected']) }}</strong>
                <small>{{ number_format($pay['count']) }} payment{{ $pay['count'] === 1 ? '' : 's' }} received</small>
            </article>
            <article class="card report-metric warning">
                <span>Outstanding balance</span>
                <strong>{{ $peso($b['outstanding']) }}</strong>
                <small>{{ number_format($b['overdueClients']) }} client{{ $b['overdueClients'] === 1 ? '' : 's' }} with overdue bills</small>
            </article>
            <article class="card report-metric danger">
                <span>Net payroll</span>
                <strong>{{ $peso($payroll['net']) }}</strong>
                <small>{{ number_format($payroll['entries']) }} payroll {{ $payroll['entries'] === 1 ? 'entry' : 'entries' }}</small>
            </article>
        </section>

        <div class="report-tables">
            {{-- The tables stay on the page even when nothing was recorded; they just read zero. --}}
            @if (\App\Support\PeriodReport::isEmpty($report))
            <p class="empty report-wide">No data for {{ $periodLabel }} &middot; {{ $branchLabel }}. The tables below show zero.</p>
            @endif
            <article class="card">
                <div class="card-header"><span class="card-title">1. Billing</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Status</th><th class="num">No. of Bills</th><th class="num">Amount</th><th class="num">% of Total Billed</th></tr></thead>
                        <tbody>
                            @foreach ([['Paid', 'paid'], ['Pending', 'pending'], ['Overdue', 'overdue']] as [$label, $key])
                            <tr><td>{{ $label }}</td><td class="num">{{ number_format($b[$key . 'Count']) }}</td><td class="num">{{ $peso($b[$key . 'Amount']) }}</td><td class="num">{{ $share($b[$key . 'Amount'], $b['amount']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td>Total billed</td><td class="num">{{ number_format($b['count']) }}</td><td class="num">{{ $peso($b['amount']) }}</td><td class="num">{{ $b['amount'] > 0 ? '100.0%' : '—' }}</td></tr></tfoot>
                    </table>
                </div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Details</th><th class="num">Amount / Count</th></tr></thead>
                        <tbody>
                            <tr><td>Clients billed</td><td class="num">{{ number_format($b['billedClients']) }}</td></tr>
                            <tr><td>Clients with overdue bills</td><td class="num">{{ number_format($b['overdueClients']) }}</td></tr>
                            @if ($b['cancelledCount'] > 0)
                            <tr><td>Cancelled bills (not counted above)</td><td class="num">{{ number_format($b['cancelledCount']) }}</td></tr>
                            @endif
                        </tbody>
                        <tfoot><tr><td>Outstanding balance (after partial payments)</td><td class="num">{{ $peso($b['outstanding']) }}</td></tr></tfoot>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-header"><span class="card-title">2. Collections</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Payment Method</th><th class="num">No. of Payments</th><th class="num">Amount</th><th class="num">% of Collections</th></tr></thead>
                        <tbody>
                            @forelse ($pay['methods'] as $method)
                            <tr><td>{{ \App\Support\ReportFormat::paymentMethod($method->payment_method) }}</td><td class="num">{{ number_format($method->payment_count) }}</td><td class="num">{{ $peso($method->total) }}</td><td class="num">{{ $share($method->total, $pay['collected']) }}</td></tr>
                            @empty
                            <tr><td colspan="4" class="empty">No payments received in this period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr><td>Total payments received</td><td class="num">{{ number_format($pay['count']) }}</td><td class="num">{{ $peso($pay['collected']) }}</td><td class="num">{{ $pay['collected'] > 0 ? '100.0%' : '—' }}</td></tr></tfoot>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-header"><span class="card-title">3. Clients</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Details</th><th class="num">Amount / Count</th></tr></thead>
                        <tbody>
                            <tr><td>New installations</td><td class="num">{{ number_format($report['clients']['new']) }}</td></tr>
                            <tr><td>Monthly rate of the new installations</td><td class="num">{{ $peso($report['clients']['monthly']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-header"><span class="card-title">4. Attendance</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Details</th><th class="num">Amount / Count</th></tr></thead>
                        <tbody>
                            <tr><td>Employees on record</td><td class="num">{{ number_format($att['employees']) }}</td></tr>
                            <tr><td>Attendance records</td><td class="num">{{ number_format($att['records']) }}</td></tr>
                            <tr><td>Present</td><td class="num">{{ number_format($att['present']) }}</td></tr>
                            <tr><td>Late</td><td class="num">{{ number_format($att['late']) }}</td></tr>
                            <tr><td>Absent</td><td class="num">{{ number_format($att['absent']) }}</td></tr>
                            <tr><td>Total hours worked</td><td class="num">{{ $hours }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-header"><span class="card-title">5. Payroll</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Details</th><th class="num">Amount / Count</th></tr></thead>
                        <tbody>
                            <tr><td>Payroll entries</td><td class="num">{{ number_format($payroll['entries']) }}</td></tr>
                            <tr><td>Gross pay</td><td class="num">{{ $peso($payroll['gross']) }}</td></tr>
                            <tr><td>Less: total deductions</td><td class="num">{{ $peso($payroll['deductions']) }}</td></tr>
                        </tbody>
                        <tfoot><tr><td>Net pay</td><td class="num">{{ $peso($payroll['net']) }}</td></tr></tfoot>
                    </table>
                </div>
            </article>

            @if ($report['requests'] !== null)
            @php($req = $report['requests'])
            <article class="card">
                <div class="card-header"><span class="card-title">6. Inventory Requests</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Status</th><th class="num">No. of Requests</th></tr></thead>
                        <tbody>
                            <tr><td>Approved</td><td class="num">{{ number_format($req['approved']) }}</td></tr>
                            <tr><td>Pending</td><td class="num">{{ number_format($req['pending']) }}</td></tr>
                            <tr><td>Denied</td><td class="num">{{ number_format($req['denied']) }}</td></tr>
                        </tbody>
                        <tfoot><tr><td>Total requests (quantity asked for: {{ number_format($req['quantity']) }})</td><td class="num">{{ number_format($req['total']) }}</td></tr></tfoot>
                    </table>
                </div>
            </article>
            @endif

            @if ($report['branches'] !== null)
            @php($br = $report['branches'])
            <article class="card report-wide">
                <div class="card-header"><span class="card-title">{{ $report['requests'] !== null ? '7' : '6' }}. By Branch</span></div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead><tr><th>Branch</th><th class="num">New Clients</th><th class="num">Billed</th><th class="num">Collected</th><th class="num">Outstanding</th><th class="num">Net Payroll</th></tr></thead>
                        <tbody>
                            @forelse ($br['rows'] as $row)
                            <tr><td>{{ $row['branch'] }}</td><td class="num">{{ number_format($row['newClients']) }}</td><td class="num">{{ $peso($row['billed']) }}</td><td class="num">{{ $peso($row['collected']) }}</td><td class="num">{{ $peso($row['outstanding']) }}</td><td class="num">{{ $peso($row['payroll']) }}</td></tr>
                            @empty
                            <tr><td colspan="6" class="empty">No activity in this period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr><td>Total</td><td class="num">{{ number_format($br['total']['newClients']) }}</td><td class="num">{{ $peso($br['total']['billed']) }}</td><td class="num">{{ $peso($br['total']['collected']) }}</td><td class="num">{{ $peso($br['total']['outstanding']) }}</td><td class="num">{{ $peso($br['total']['payroll']) }}</td></tr></tfoot>
                    </table>
                </div>
            </article>
            @endif
        </div>
    </main>
    <script>
        // Picking a month fills the dates and shows that month straight away.
        document.getElementById('month')?.addEventListener('change', function () {
            if (!this.value) return;
            const [year, month] = this.value.split('-');
            document.getElementById('from').value = `${year}-${month}-01`;
            document.getElementById('to').value = `${year}-${month}-${new Date(year, month, 0).getDate().toString().padStart(2, '0')}`;
            document.getElementById('reportFilter').requestSubmit();
        });

        // The branch box (super admin): suggestions while typing. What is typed must be one branch, or nothing for every branch.
        document.addEventListener('DOMContentLoaded', function () {
            const box = document.getElementById('branch');
            if (!box || box.tagName !== 'INPUT') return;
            const branches = @json($reportBranches);
            createSuggestionList(
                box,
                document.getElementById('branchSuggestions'),
                function () { return branches; },
                function (name) { box.value = name; box.setCustomValidity(''); },
                { emptyText: 'No branch matches.' }
            );
            box.addEventListener('focus', function () { box.select(); });
            box.addEventListener('input', function () { box.setCustomValidity(''); });

            // A name typed in full, or the start of only one branch, is completed; anything else is not a branch.
            function settle() {
                const typed = box.value.trim().toLocaleLowerCase();
                if (typed === '') { box.value = ''; return true; }
                const exact = branches.filter(function (name) { return name.toLocaleLowerCase() === typed; });
                const starts = branches.filter(function (name) { return name.toLocaleLowerCase().startsWith(typed); });
                const found = exact.length === 1 ? exact : starts;
                if (found.length !== 1) return false;
                box.value = found[0];
                return true;
            }
            document.getElementById('reportFilter').addEventListener('submit', function (event) {
                if (settle()) return;
                event.preventDefault();
                box.setCustomValidity('Choose a branch from the suggestions, or leave it empty for every branch.');
                box.reportValidity();
            });
        });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body></html>

@php
    /** @var array $generatedReport */
    $reportTypes = $reportTypes ?? [];
    $summary = $generatedReport['summary'] ?? ['total' => 0, 'completed' => 0, 'pending' => 0, 'cancelled' => 0, 'overdue' => 0, 'amount' => 0, 'quantity' => 0];
    $records = $generatedReport['records'] ?? [];
    $viewType = $generatedReport['report_type'] ?? 'custom';
    $dateFrom = $generatedReport['date_from'] ?? '';
    $dateTo = $generatedReport['date_to'] ?? '';

    $periodLabel = '';
    if ($dateFrom === $dateTo && $dateFrom !== '') {
        $periodLabel = date('F d, Y', strtotime($dateFrom));
    } elseif ($dateFrom && $dateTo) {
        $periodLabel = date('M d, Y', strtotime($dateFrom)) . ' to ' . date('M d, Y', strtotime($dateTo));
    } elseif ($dateFrom) {
        $periodLabel = 'From ' . date('M d, Y', strtotime($dateFrom));
    } elseif ($dateTo) {
        $periodLabel = 'Until ' . date('M d, Y', strtotime($dateTo));
    } else {
        $periodLabel = 'All Dates';
    }

    $exportParams = [
        'report_name'     => $generatedReport['report_name'] ?? 'JFTECH Activity Report',
        'report_view_type' => $viewType,
    ];
    foreach (($generatedReport['filters'] ?? []) as $k => $v) {
        if ($v !== '' && $v !== 'all') {
            $exportParams[$k] = $v;
        }
    }
    if ($dateFrom) $exportParams['from'] = $dateFrom;
    if ($dateTo)   $exportParams['to']   = $dateTo;
    if ($viewType === 'yearly' && !empty($generatedReport['selected_year'])) {
        $exportParams['year'] = $generatedReport['selected_year'];
    }
    $exportQuery = http_build_query($exportParams);

    $performance = $generatedReport['performance'] ?? null;
    $chartData = $generatedReport['chart_data'] ?? null;
    $previousSummary = $generatedReport['previous_summary'] ?? null;
    $comparisonPeriod = $generatedReport['comparison_period'] ?? '';
    $pctChanges = $generatedReport['percentage_changes'] ?? [];
    $dailyBreakdown = $generatedReport['daily_breakdown'] ?? null;
    $monthlyBreakdown = $generatedReport['monthly_breakdown'] ?? null;
@endphp

<div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <span class="card-title">{{ htmlspecialchars($generatedReport['report_name'] ?? 'Activity Report') }}</span>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <span class="badge badge-info">Period: {{ $periodLabel }}</span>
        <span class="badge badge-info">Generated: {{ $generatedReport['generated_at'] ?? date('Y-m-d H:i:s') }}</span>
        <span class="badge badge-success">By: {{ htmlspecialchars($generatedReport['generated_by'] ?? 'System') }}</span>
    </div>
</div>

<div class="card-body" style="padding: 1rem;">
    <!-- ==================== Summary KPI Cards ==================== -->
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-value">{{ number_format($summary['total']) }}</div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--success);">
            <div class="stat-value" style="color: var(--success);">{{ number_format($summary['completed']) }}</div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--warning);">
            <div class="stat-value" style="color: var(--warning);">{{ number_format($summary['pending']) }}</div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--danger);">
            <div class="stat-value" style="color: var(--danger);">{{ number_format($summary['cancelled']) }}</div>
            <div class="stat-label">Cancelled</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--info);">
            <div class="stat-value" style="color: var(--info);">{{ number_format($summary['overdue']) }}</div>
            <div class="stat-label">Overdue</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--success);">
            <div class="stat-value" style="color: var(--success); font-size: 1.4rem;">PHP {{ number_format((float) $summary['amount'], 2) }}</div>
            <div class="stat-label">Total Amount</div>
        </div>
        <div class="stat-card" style="--stat-color: var(--accent);">
            <div class="stat-value">{{ number_format((int) $summary['quantity']) }}</div>
            <div class="stat-label">Total Qty</div>
        </div>
    </div>

    <!-- ==================== Performance Summary ==================== -->
    @if ($performance)
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="padding: 0.75rem 1.5rem;">
            <span class="card-title" style="font-size: 1rem;">Performance Summary</span>
            <span class="badge badge-info">{{ $performance['period_type'] === 'day' ? $performance['days_in_period'] . ' days' : ($performance['period_type'] === 'month' ? $performance['days_in_period'] . ' months' : $performance['days_in_period'] . ' periods') }}</span>
        </div>
        <div class="card-body" style="padding: 1rem;">
            <div class="stats-grid" style="margin-bottom: 0;">
                <div class="stat-card" style="--stat-color: var(--accent);">
                    <div class="stat-value" style="font-size: 1.5rem;">{{ number_format($performance['avg_per_day'], 1) }}</div>
                    <div class="stat-label">Avg Records/{{ $performance['period_type'] === 'day' ? 'Day' : 'Month' }}</div>
                </div>
                <div class="stat-card" style="--stat-color: var(--success);">
                    <div class="stat-value" style="font-size: 1.5rem; color: var(--success);">{{ number_format($performance['avg_amount_per_day'], 2) }}</div>
                    <div class="stat-label">Avg Amount/{{ $performance['period_type'] === 'day' ? 'Day' : 'Month' }}</div>
                </div>
                <div class="stat-card" style="--stat-color: var(--info);">
                    <div class="stat-value" style="font-size: 1.5rem;">{{ number_format($performance['completion_rate'], 1) }}%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
                <div class="stat-card" style="--stat-color: var(--warning);">
                    <div class="stat-value" style="font-size: 1.5rem; color: var(--warning);">{{ number_format($performance['overdue_rate'], 1) }}%</div>
                    <div class="stat-label">Overdue Rate</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ==================== Chart ==================== -->
    @if ($chartData && !empty($chartData['labels']))
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="padding: 0.75rem 1.5rem;">
            <span class="card-title" style="font-size: 1rem;">
                @if ($viewType === 'yearly')
                    Monthly Activity Trend
                @elseif ($viewType === 'monthly')
                    Daily Activity Breakdown
                @elseif ($viewType === 'daily')
                    Activity by Report Type
                @else
                    Activity Breakdown
                @endif
            </span>
        </div>
        <div class="card-body" style="padding: 1rem;">
            <div id="reportChart" class="report-chart"></div>
        </div>
    </div>
    @endif

    <!-- ==================== Comparison with Previous Period ==================== -->
    @if ($previousSummary)
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="padding: 0.75rem 1.5rem;">
            <span class="card-title" style="font-size: 1rem;">Comparison with Previous Period</span>
            <span class="badge badge-info">{{ $viewType === 'monthly' ? 'Previous Month' : 'Previous Year' }}: {{ htmlspecialchars($comparisonPeriod) }}</span>
        </div>
        <div class="card-body" style="padding: 1rem;">
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">
                Comparing current period metrics against {{ htmlspecialchars($comparisonPeriod) }}.
                @php
                    $hasChange = false;
                    foreach ($pctChanges as $metric => $change) {
                        if ($change !== null) { $hasChange = true; break; }
                    }
                @endphp
                @if (!$hasChange)
                    <span style="color: var(--warning);">Some metrics have zero baseline — percentage change unavailable.</span>
                @endif
            </p>
            <div class="stats-grid" style="margin-bottom: 0;">
                <div class="stat-card" style="--stat-color: var(--accent);">
                    <div class="stat-value">{{ number_format($previousSummary['total']) }}</div>
                    <div class="stat-label">Prev Total</div>
                    @if (isset($pctChanges['total']) && $pctChanges['total'] !== null)
                    <div class="stat-change {{ $pctChanges['total'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['total'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['total']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--success);">
                    <div class="stat-value" style="color: var(--success);">{{ number_format($previousSummary['completed']) }}</div>
                    <div class="stat-label">Prev Completed</div>
                    @if (isset($pctChanges['completed']) && $pctChanges['completed'] !== null)
                    <div class="stat-change {{ $pctChanges['completed'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['completed'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['completed']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--warning);">
                    <div class="stat-value" style="color: var(--warning);">{{ number_format($previousSummary['pending']) }}</div>
                    <div class="stat-label">Prev Pending</div>
                    @if (isset($pctChanges['pending']) && $pctChanges['pending'] !== null)
                    <div class="stat-change {{ $pctChanges['pending'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['pending'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['pending']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--danger);">
                    <div class="stat-value" style="color: var(--danger);">{{ number_format($previousSummary['cancelled']) }}</div>
                    <div class="stat-label">Prev Cancelled</div>
                    @if (isset($pctChanges['cancelled']) && $pctChanges['cancelled'] !== null)
                    <div class="stat-change {{ $pctChanges['cancelled'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['cancelled'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['cancelled']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--info);">
                    <div class="stat-value" style="color: var(--info);">{{ number_format($previousSummary['overdue']) }}</div>
                    <div class="stat-label">Prev Overdue</div>
                    @if (isset($pctChanges['overdue']) && $pctChanges['overdue'] !== null)
                    <div class="stat-change {{ $pctChanges['overdue'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['overdue'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['overdue']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--success);">
                    <div class="stat-value" style="color: var(--success); font-size: 1.4rem;">PHP {{ number_format((float) $previousSummary['amount'], 2) }}</div>
                    <div class="stat-label">Prev Amount</div>
                    @if (isset($pctChanges['amount']) && $pctChanges['amount'] !== null)
                    <div class="stat-change {{ $pctChanges['amount'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $pctChanges['amount'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($pctChanges['amount']), 2) }}%
                    </div>
                    @endif
                </div>
                <div class="stat-card" style="--stat-color: var(--accent);">
                    <div class="stat-value">{{ number_format((int) $previousSummary['quantity']) }}</div>
                    <div class="stat-label">Prev Qty</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ==================== Daily Breakdown ==================== -->
    @if ($dailyBreakdown && !empty($dailyBreakdown))
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="padding: 0.75rem 1.5rem;">
            <span class="card-title" style="font-size: 1rem;">
                @if ($viewType === 'monthly')
                    Daily Breakdown — {{ date('F Y', strtotime($dateFrom)) }}
                @elseif ($viewType === 'yearly')
                    Daily Breakdown — {{ date('Y', strtotime($dateFrom)) }}
                @else
                    Daily Breakdown
                @endif
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Date</th>
                            <th style="width: 12%;">Total</th>
                            <th style="width: 13%;">Completed</th>
                            <th style="width: 13%;">Pending</th>
                            <th style="width: 13%;">Cancelled</th>
                            <th style="width: 10%;">Overdue</th>
                            <th style="width: 10%;">Amount</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyBreakdown as $day)
                        <tr>
                            <td>{{ date('M d', strtotime($day['day'])) }}</td>
                            <td><strong>{{ number_format($day['total']) }}</strong></td>
                            <td style="color: var(--success);">{{ number_format($day['completed']) }}</td>
                            <td style="color: var(--warning);">{{ number_format($day['pending']) }}</td>
                            <td style="color: var(--danger);">{{ number_format($day['cancelled']) }}</td>
                            <td style="color: var(--info);">{{ number_format($day['overdue']) }}</td>
                            <td>{{ $day['amount'] > 0 ? 'PHP ' . number_format((float) $day['amount'], 2) : '-' }}</td>
                            <td>{{ $day['quantity'] > 0 ? number_format((int) $day['quantity']) : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ==================== Monthly Breakdown (Yearly only) ==================== -->
    @if ($monthlyBreakdown && !empty($monthlyBreakdown))
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="padding: 0.75rem 1.5rem;">
            <span class="card-title" style="font-size: 1rem;">Monthly Breakdown — {{ $generatedReport['selected_year'] }}</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 16%;">Month</th>
                            <th style="width: 11%;">Total</th>
                            <th style="width: 12%;">Completed</th>
                            <th style="width: 12%;">Pending</th>
                            <th style="width: 12%;">Cancelled</th>
                            <th style="width: 10%;">Overdue</th>
                            <th style="width: 12%;">Amount</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyBreakdown as $m)
                        <tr>
                            <td>{{ $m['label'] }}</td>
                            <td><strong>{{ number_format($m['total']) }}</strong></td>
                            <td style="color: var(--success);">{{ number_format($m['completed']) }}</td>
                            <td style="color: var(--warning);">{{ number_format($m['pending']) }}</td>
                            <td style="color: var(--danger);">{{ number_format($m['cancelled']) }}</td>
                            <td style="color: var(--info);">{{ number_format($m['overdue']) }}</td>
                            <td>{{ $m['amount'] > 0 ? 'PHP ' . number_format((float) $m['amount'], 2) : '-' }}</td>
                            <td>{{ $m['quantity'] > 0 ? number_format((int) $m['quantity']) : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- ==================== Export Actions ==================== -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
        <button type="button" class="btn btn-sm btn-primary" onclick="window.open('/pages/print_reports.php?{{ $exportQuery }}', '_blank')">Print / Save PDF</button>
        <a href="/pages/reports_export.php?{{ $exportQuery }}" class="btn btn-sm btn-success">Export CSV</a>
        <a href="/pages/reports_excel.php?{{ $exportQuery }}" class="btn btn-sm btn-info">Export Excel</a>
    </div>

    <!-- ==================== Activity Records Table ==================== -->
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 11%;">Date/Time</th>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 8%;">Type</th>
                    <th>Activity</th>
                    <th style="width: 12%;">Person</th>
                    <th style="width: 9%;">Status</th>
                    <th style="width: 9%;">Amount</th>
                    <th style="width: 6%;">Qty</th>
                    <th style="width: 15%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $act)
                <tr>
                    <td>{{ $act['activity_datetime'] ? date('M d, Y h:i A', strtotime($act['activity_datetime'])) : '-' }}</td>
                    <td>{{ $act['record_id'] }}</td>
                    <td><span class="badge badge-info">{{ htmlspecialchars($act['report_type']) }}</span></td>
                    <td>{{ $reportTypes[$act['report_type']] ?? $act['report_type'] }}</td>
                    <td>{{ htmlspecialchars($act['person'] ?: '-') }}</td>
                    <td>@include('components.status-badge', ['status' => $act['status']])</td>
                    <td>{{ $act['amount'] !== null ? 'PHP ' . number_format((float) $act['amount'], 2) : '-' }}</td>
                    <td>{{ $act['quantity'] !== null ? number_format((int) $act['quantity']) : '-' }}</td>
                    <td style="color: var(--text-muted); font-size: 0.82rem;">{{ htmlspecialchars($act['remarks'] ?: '-') }}</td>
                </tr>
                @endforeach
                @if (empty($records))
                <tr><td colspan="9" style="text-align:center; color: var(--text-muted);">No records match the selected filters.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@if (! empty($summary['total']))
<div class="card-footer">
    <div style="font-size: 0.82rem; color: var(--text-muted);">
        Showing {{ number_format(count($records)) }} of {{ number_format($summary['total']) }} matching records.
        Generated on {{ date('M d, Y h:i A') }}.
    </div>
</div>
@endif

@if ($chartData && !empty($chartData['labels']))
<script>
(function() {
    var chartData = @json($chartData);
    var target = document.getElementById('reportChart');
    if (!target || !chartData.labels || chartData.labels.length === 0) {
        if (target) target.innerHTML = '<div class="chart-empty"><strong>No data</strong><span>No chart data available.</span></div>';
        return;
    }
    var labels = chartData.labels;
    var totals = chartData.totals || [];
    var max = Math.max.apply(null, totals.concat([0]));
    if (max === 0) {
        target.innerHTML = '<div class="chart-empty"><strong>No data</strong><span>No activity recorded for this period.</span></div>';
        return;
    }
    var height = 200, topPad = 20, bottomPad = 30, leftPad = 50, rightPad = 20;
    var width = Math.max(480, labels.length * 48);
    var plotW = width - leftPad - rightPad;
    var plotH = height - topPad - bottomPad;
    var barW = plotW / labels.length * 0.6;
    var barGap = plotW / labels.length;

    var colors = { completed: '#00e676', pending: '#ffab00', cancelled: '#ff1744', overdue: '#2979ff' };
    var categories = ['completed', 'pending', 'cancelled', 'overdue'];

    var svg = '<svg class="chart-svg" viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-label="Activity chart">';

    // Y-axis grid + labels
    var gridSteps = 5;
    for (var i = 0; i <= gridSteps; i++) {
        var frac = i / gridSteps;
        var y = topPad + plotH - (plotH * frac);
        svg += '<line x1="' + leftPad + '" y1="' + y + '" x2="' + (width - rightPad) + '" y2="' + y + '" class="chart-grid"/>';
        var val = Math.round(max * frac);
        svg += '<text x="' + (leftPad - 8) + '" y="' + (y + 4) + '" class="chart-axis" text-anchor="end">' + val + '</text>';
    }

    // Bars (stacked)
    var curX = leftPad;
    for (var i = 0; i < labels.length; i++) {
        var x = leftPad + (i * barGap) + (barGap - barW) / 2;
        var stackH = 0;
        for (var j = 0; j < categories.length; j++) {
            var cat = categories[j];
            var val = (chartData[cat] || [])[i] || 0;
            if (val > 0) {
                var h = (val / max) * plotH;
                var color = colors[cat];
                svg += '<rect x="' + x + '" y="' + (topPad + plotH - stackH - h) + '" width="' + barW + '" height="' + h + '" fill="' + color + '" rx="2"><title>' + labels[i] + ' ' + cat + ': ' + val + '</title></rect>';
                stackH += h;
            }
        }
        // Total label
        svg += '<text x="' + (x + barW / 2) + '" y="' + (topPad + plotH + 14) + '" class="chart-axis" text-anchor="middle" font-size="10">' + labels[i] + '</text>';
        // Total value on top of bar
        if (totals[i] > 0) {
            svg += '<text x="' + (x + barW / 2) + '" y="' + (topPad + plotH - stackH - 4) + '" class="chart-axis" text-anchor="middle" font-size="9" fill="#e0f2fe" font-weight="600">' + totals[i] + '</text>';
        }
    }

    // X-axis line
    svg += '<line x1="' + leftPad + '" y1="' + (topPad + plotH) + '" x2="' + (width - rightPad) + '" y2="' + (topPad + plotH) + '" stroke="var(--border-color)" stroke-width="1"/>';
    svg += '</svg>';

    // Legend
    var legend = '<div style="display:flex;gap:1rem;justify-content:center;margin-top:0.75rem;font-size:0.8rem;">';
    categories.forEach(function(cat) {
        legend += '<span style="display:inline-flex;align-items:center;gap:0.35rem;"><span style="width:12px;height:12px;border-radius:3px;background:' + colors[cat] + '"></span>' + cat.charAt(0).toUpperCase() + cat.slice(1) + '</span>';
    });
    legend += '</div>';
    target.innerHTML = svg + legend;
})();
</script>
@endif

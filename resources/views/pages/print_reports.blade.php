<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <title>{{ htmlspecialchars($reportName) }}</title>
    <style>
        @media print { *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } }
        @media print { .doc-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; flex-wrap: nowrap; } .doc-header .print-company-brand { min-width: 0; } .doc-title { text-align: right; } }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
            margin: 12px;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .doc-header .company {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #1e3a8a;
        }
        .doc-header .sub {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }
        .print-company-brand { display: flex; align-items: flex-start; gap: 10px; }
        .print-company-logo { width: auto; height: 48px; object-fit: contain; }
        .doc-title {
            text-align: right;
            font-size: 14px;
            color: #334155;
        }
        .doc-title strong { font-size: 16px; color: #0f172a; }
        .meta {
            font-size: 12px;
            color: #475569;
            margin-bottom: 14px;
        }
        .meta strong { color: #0f172a; }
        .summary-bar {
            display: flex;
            gap: 24px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .summary-bar .box {
            font-size: 12px;
            color: #475569;
        }
        .summary-bar .box strong { font-size: 15px; color: #0f172a; }
        .stat-box {
            text-align: center;
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            min-width: 80px;
        }
        .stat-box .val { font-size: 16px; font-weight: 700; color: #0f172a; }
        .stat-box .lbl { font-size: 10px; color: #64748b; text-transform: uppercase; }
        .stat-box.completed .val { color: #05872a; }
        .stat-box.pending .val { color: #b48200; }
        .stat-box.cancelled .val { color: #b12020; }
        .stat-box.overdue .val { color: #1665d7; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 20px;
        }
        thead th {
            background: #1e3a8a;
            color: #ffffff;
            text-align: left;
            padding: 7px 8px;
            border: 1px solid #1e3a8a;
            white-space: nowrap;
            font-weight: 600;
        }
        tbody td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .amount { text-align: right; white-space: nowrap; }
        tfoot td {
            padding: 8px 8px;
            border: 1px solid #1e3a8a;
            font-weight: 700;
            background: #eef2ff;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .badge-cell { white-space: nowrap; }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 20px 0 10px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #1e3a8a;
        }
        .comparison-table td { font-size: 11px; }
        @media (max-width: 768px) {
            body { margin: 6px; }
            .doc-header { flex-direction: column; gap: 0.5rem; }
            .doc-title { text-align: right; }
            thead th { font-size: 10px; padding: 5px 6px; }
            tbody td { font-size: 10px; padding: 5px 6px; }
        }
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { margin: 0; }
            .toolbar { display: none; }
            table { table-layout: fixed; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; page-break-inside: avoid; }
            th, td { overflow-wrap: anywhere; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px; cursor: pointer;">Print / Save as PDF</button>
    </div>

    <div class="doc-header">
        @include('partials.print_company')
        <div class="doc-title">
            <strong>{{ strtoupper($reportName) }}</strong><br>
            Prepared: {{ date('M d, Y h:i A') }}
        </div>
    </div>

    <div class="meta">
        Period: <strong>{{ htmlspecialchars($rangeLabel) }}</strong> &nbsp;|&nbsp;
        View Type: <strong>{{ ucfirst(htmlspecialchars($reportViewType)) }}</strong> &nbsp;|&nbsp;
        Total Entries: <strong>{{ number_format($summary['total']) }}</strong> &nbsp;|&nbsp;
        Completed: <strong>{{ number_format($summary['completed']) }}</strong> &nbsp;|&nbsp;
        Pending: <strong>{{ number_format($summary['pending']) }}</strong> &nbsp;|&nbsp;
        Cancelled: <strong>{{ number_format($summary['cancelled']) }}</strong> &nbsp;|&nbsp;
        Overdue: <strong>{{ number_format($summary['overdue']) }}</strong>
    </div>

    <div class="summary-bar">
        <div class="box">Total Amount: <strong>₱{{ number_format((float) $summary['amount'], 2) }}</strong></div>
        <div class="box">Total Quantity: <strong>{{ number_format((int) $summary['quantity']) }}</strong></div>
        @php
            $completionRate = $summary['total'] > 0 ? round($summary['completed'] / $summary['total'] * 100, 1) : 0;
            $overdueRate = $summary['total'] > 0 ? round($summary['overdue'] / $summary['total'] * 100, 1) : 0;
        @endphp
        <div class="box">Completion Rate: <strong>{{ $completionRate }}%</strong></div>
        <div class="box">Overdue Rate: <strong>{{ $overdueRate }}%</strong></div>
        @if ($performance)
        <div class="box">Average Entries per {{ $performance['period_type'] === 'day' ? 'Day' : 'Month' }}: <strong>{{ number_format($performance['avg_per_day'], 1) }}</strong></div>
        <div class="box">Average Amount per {{ $performance['period_type'] === 'day' ? 'Day' : 'Month' }}: <strong>₱{{ number_format($performance['avg_amount_per_day'], 2) }}</strong></div>
        @endif
    </div>

    <!-- ==================== Comparison with Previous Period ==================== -->
    @if ($previousSummary && $reportViewType !== 'daily' && $reportViewType !== 'custom')
    <div class="section-title">Comparison with Previous Period ({{ htmlspecialchars($comparisonPeriod) }})</div>
    <table class="comparison-table" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Metric</th>
                <th style="text-align: right;">Current</th>
                <th style="text-align: right;">Previous</th>
                <th style="text-align: right;">Change (%)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Entries</td>
                <td class="amount">{{ number_format($summary['total']) }}</td>
                <td class="amount">{{ number_format($previousSummary['total']) }}</td>
                <td class="amount">{{ isset($percentageChanges['total']) && $percentageChanges['total'] !== null ? number_format($percentageChanges['total'], 2) . '%' : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Completed</td>
                <td class="amount">{{ number_format($summary['completed']) }}</td>
                <td class="amount">{{ number_format($previousSummary['completed']) }}</td>
                <td class="amount">{{ isset($percentageChanges['completed']) && $percentageChanges['completed'] !== null ? number_format($percentageChanges['completed'], 2) . '%' : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Pending</td>
                <td class="amount">{{ number_format($summary['pending']) }}</td>
                <td class="amount">{{ number_format($previousSummary['pending']) }}</td>
                <td class="amount">{{ isset($percentageChanges['pending']) && $percentageChanges['pending'] !== null ? number_format($percentageChanges['pending'], 2) . '%' : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Cancelled</td>
                <td class="amount">{{ number_format($summary['cancelled']) }}</td>
                <td class="amount">{{ number_format($previousSummary['cancelled']) }}</td>
                <td class="amount">{{ isset($percentageChanges['cancelled']) && $percentageChanges['cancelled'] !== null ? number_format($percentageChanges['cancelled'], 2) . '%' : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Overdue</td>
                <td class="amount">{{ number_format($summary['overdue']) }}</td>
                <td class="amount">{{ number_format($previousSummary['overdue']) }}</td>
                <td class="amount">{{ isset($percentageChanges['overdue']) && $percentageChanges['overdue'] !== null ? number_format($percentageChanges['overdue'], 2) . '%' : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Total Amount</td>
                <td class="amount">₱{{ number_format((float) $summary['amount'], 2) }}</td>
                <td class="amount">₱{{ number_format((float) $previousSummary['amount'], 2) }}</td>
                <td class="amount">{{ isset($percentageChanges['amount']) && $percentageChanges['amount'] !== null ? number_format($percentageChanges['amount'], 2) . '%' : 'N/A' }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- ==================== Daily Breakdown ==================== -->
    @if ($dailyBreakdown && !empty($dailyBreakdown))
    <div class="section-title">{{ $reportViewType === 'yearly' ? 'Daily Breakdown' : 'Daily Breakdown' }}</div>
    <table style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Total</th>
                <th>Completed</th>
                <th>Pending</th>
                <th>Cancelled</th>
                <th>Overdue</th>
                <th>Amount</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dailyBreakdown as $day)
            <tr>
                <td>{{ date('M d, Y', strtotime($day['day'])) }}</td>
                <td>{{ number_format($day['total']) }}</td>
                <td>{{ number_format($day['completed']) }}</td>
                <td>{{ number_format($day['pending']) }}</td>
                <td>{{ number_format($day['cancelled']) }}</td>
                <td>{{ number_format($day['overdue']) }}</td>
                <td class="amount">{{ $day['amount'] > 0 ? '₱' . number_format((float) $day['amount'], 2) : '-' }}</td>
                <td>{{ $day['quantity'] > 0 ? number_format((int) $day['quantity']) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- ==================== Monthly Breakdown (Yearly) ==================== -->
    @if ($monthlyBreakdown && !empty($monthlyBreakdown))
    <div class="section-title">Monthly Breakdown — {{ $selectedYear }}</div>
    <table style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Month</th>
                <th>Total</th>
                <th>Completed</th>
                <th>Pending</th>
                <th>Cancelled</th>
                <th>Overdue</th>
                <th>Amount</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($monthlyBreakdown as $m)
            <tr>
                <td>{{ $m['label'] }}</td>
                <td>{{ number_format($m['total']) }}</td>
                <td>{{ number_format($m['completed']) }}</td>
                <td>{{ number_format($m['pending']) }}</td>
                <td>{{ number_format($m['cancelled']) }}</td>
                <td>{{ number_format($m['overdue']) }}</td>
                <td class="amount">{{ $m['amount'] > 0 ? '₱' . number_format((float) $m['amount'], 2) : '-' }}</td>
                <td>{{ $m['quantity'] > 0 ? number_format((int) $m['quantity']) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- ==================== Detailed Records Table ==================== -->
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date & Time</th>
                <th style="width: 5%;">Record No.</th>
                <th style="width: 9%;">Record Type</th>
                <th>Activity</th>
                <th style="width: 12%;">Name</th>
                <th style="width: 9%;">Status</th>
                <th class="amount" style="width: 9%;">Amount</th>
                <th class="amount" style="width: 6%;">Quantity</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($records as $act)
            <tr>
                <td>{{ $act['activity_datetime'] ? date('M d, Y h:i A', strtotime($act['activity_datetime'])) : '-' }}</td>
                <td>{{ $act['record_id'] }}</td>
                <td>{{ $reportTypes[$act['report_type']] ?? ucfirst(str_replace('_', ' ', $act['report_type'])) }}</td>
                <td>{{ htmlspecialchars($act['activity_type']) }}</td>
                <td>{{ htmlspecialchars($act['person'] ?: '-') }}</td>
                <td class="badge-cell">{{ ucfirst(htmlspecialchars($act['status'])) }}</td>
                <td class="amount">{{ $act['amount'] !== null ? '₱' . number_format((float) $act['amount'], 2) : '-' }}</td>
                <td class="amount">{{ $act['quantity'] !== null ? number_format((int) $act['quantity']) : '-' }}</td>
                <td>{{ htmlspecialchars($act['remarks'] ?: '-') }}</td>
            </tr>
            @endforeach
            @if (empty($records))
            <tr><td colspan="9" style="text-align: center; color: #64748b; padding: 18px;">No records found for the selected period.</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Prepared by JFTECH WiFi Billing &amp; Management System.
        Report Name: {{ htmlspecialchars($reportName) }} | Period: {{ htmlspecialchars($rangeLabel) }} | Date Prepared: {{ date('M d, Y h:i A') }}
    </div>

    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>

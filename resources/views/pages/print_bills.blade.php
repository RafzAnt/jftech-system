<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Report</title>
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
        .doc-title strong {
            font-size: 16px;
            color: #0f172a;
        }
        .meta {
            font-size: 12px;
            color: #475569;
            margin-bottom: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead th {
            background: #1e3a8a;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #1e3a8a;
        }
        tbody td {
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .amount { text-align: right; white-space: nowrap; }
        tfoot td {
            padding: 9px 10px;
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
        .no-print { display: none; }
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
            <strong>BILLING REPORT</strong><br>
            Generated: {{ date('M d, Y h:i A') }}
        </div>
    </div>

    <div class="meta">
        Period: <strong>{{ htmlspecialchars($periodLabel) }}</strong> &nbsp;|&nbsp;
        @if ($statusLabel)
        Status: <strong>{{ htmlspecialchars($statusLabel) }}</strong> &nbsp;|&nbsp;
        @endif
        Total Bills: <strong>{{ number_format($totalBills) }}</strong> &nbsp;|&nbsp;
        Total Amount: <strong>PHP {{ number_format($totalAmount, 2) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Bill #</th>
                <th>Client</th>
                <th style="width: 12%;">Code</th>
                <th style="width: 14%;">Period Start</th>
                <th style="width: 14%;">Period End</th>
                <th style="width: 14%;">Due Date</th>
                <th class="amount" style="width: 12%;">Amount</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bills as $bill)
            <tr>
                <td><strong>{{ htmlspecialchars($bill['bill_number']) }}</strong></td>
                <td>{{ htmlspecialchars($bill['full_name']) }}</td>
                <td>{{ htmlspecialchars($bill['client_code']) }}</td>
                <td>{{ date('M d, Y', strtotime($bill['billing_period_start'])) }}</td>
                <td>{{ date('M d, Y', strtotime($bill['billing_period_end'])) }}</td>
                <td>{{ date('M d, Y', strtotime($bill['due_date'])) }}</td>
                <td class="amount">PHP {{ number_format((float) $bill['amount'], 2) }}</td>
                <td>
                    @if ($bill['status'] === 'paid')
                        <span style="color: #16a34a; font-weight: 600;">Paid</span>
                    @elseif ($bill['status'] === 'overdue')
                        <span style="color: #dc2626; font-weight: 600;">Overdue</span>
                    @elseif ($bill['status'] === 'pending')
                        <span style="color: #d97706; font-weight: 600;">Pending</span>
                    @else
                        {{ ucfirst($bill['status']) }}
                    @endif
                </td>
            </tr>
            @endforeach
            @php
if (empty($bills)) { echo '<tr><td colspan="8" style="text-align: center; color: #64748b; padding: 18px;">No billing records found.</td></tr>'; } 
@endphp
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">GRAND TOTAL</td>
                <td class="amount">PHP {{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($totalBills) }} bills</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        This report is system-generated by JFTECH WiFi Billing & Management System.
    </div>

    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>

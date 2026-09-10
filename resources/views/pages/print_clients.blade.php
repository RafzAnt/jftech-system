<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Masterlist Report</title>
    <style>
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
        <div>
            <div class="company">JFTECH IT SOLUTIONS</div>
            <div class="sub">Main Office &bull; Purok 2, San Isidro, Consolacion, Cebu</div>
        </div>
        <div class="doc-title">
            <strong>CLIENT MASTERLIST REPORT</strong><br>
            Generated: {{ date('M d, Y h:i A') }}
        </div>
    </div>

    <div class="meta">
        @if ($barangayLabel)
        Barangay: <strong>{{ htmlspecialchars($barangayLabel) }}</strong> &nbsp;|&nbsp;
        @endif
        Total Clients: <strong>{{ number_format($totalClients) }}</strong> &nbsp;|&nbsp;
        Active: <strong>{{ number_format($activeClients) }}</strong> &nbsp;|&nbsp;
        Inactive: <strong>{{ number_format($inactiveClients) }}</strong> &nbsp;|&nbsp;
        Suspended: <strong>{{ number_format($suspendedClients) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Code</th>
                <th>Full Name</th>
                <th>Contact</th>
                <th>Barangay</th>
                <th>Plan</th>
                <th class="amount" style="width: 14%;">Monthly Rate</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($clients as $client)
            <tr>
                <td><strong>{{ htmlspecialchars($client['client_code']) }}</strong></td>
                <td>{{ htmlspecialchars($client['full_name']) }}</td>
                <td>{{ htmlspecialchars($client['contact_number'] ?: '-') }}</td>
                <td>{{ htmlspecialchars($client['barangay_name'] ?: 'N/A') }}</td>
                <td>{{ htmlspecialchars($client['wifi_plan'] ?: 'N/A') }}</td>
                <td class="amount">PHP {{ number_format((float) $client['monthly_rate'], 2) }}</td>
                <td>{{ ucfirst($client['status']) }}</td>
            </tr>
            @endforeach
            @php
if (empty($clients)) { echo '<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 18px;">No clients found.</td></tr>'; } 
@endphp
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL CLIENTS</td>
                <td class="amount">PHP {{ number_format(array_sum(array_map(function($c) { return (float) $c['monthly_rate']; }, $clients)), 2) }}</td>
                <td>{{ number_format($totalClients) }}</td>
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

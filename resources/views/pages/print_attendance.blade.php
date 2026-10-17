<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        .attendance-qr-codes {
            display: flex;
            justify-content: center;
            gap: 28px;
            margin: 0 0 18px;
        }
        .attendance-qr-code {
            min-width: 148px;
            text-align: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
        }
        .attendance-qr-code strong { display: block; color: #1e3a8a; font-size: 13px; margin-bottom: 6px; }
        .attendance-qr-code div { display: inline-block; padding: 4px; background: #fff; }
        .attendance-qr-code img,
        .attendance-qr-code canvas { display: block; width: 104px !important; height: 104px !important; }
        .attendance-qr-code small { display: block; margin-top: 6px; color: #475569; font-size: 10px; }
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
            <strong>ATTENDANCE REPORT</strong><br>
            Prepared: {{ date('M d, Y h:i A') }}
        </div>
    </div>

    <div class="meta">
        Period: <strong>{{ htmlspecialchars($periodLabel) }}</strong> &nbsp;|&nbsp;
        Total Entries: <strong>{{ number_format(count($attendanceRecords)) }}</strong> &nbsp;|&nbsp;
        Present: <strong>{{ number_format($totalPresent) }}</strong> &nbsp;|&nbsp;
        Late: <strong>{{ number_format($totalLate) }}</strong> &nbsp;|&nbsp;
        Absent: <strong>{{ number_format($totalAbsent) }}</strong>
    </div>

    @if (!empty($attendanceQrCodes))
    <section class="attendance-qr-codes" aria-label="Attendance QR codes">
        @foreach ($attendanceQrCodes as $qr)
        <div class="attendance-qr-code">
            <strong>{{ $qr['qr_type'] === 'morning' ? 'MORNING QR CODE' : 'AFTERNOON QR CODE' }}</strong>
            <div id="attendanceQr{{ ucfirst($qr['qr_type']) }}"></div>
            <small>{{ $qr['qr_code'] }}</small>
            <small>Valid: {{ date('h:i A', strtotime($qr['valid_from'])) }} - {{ date('h:i A', strtotime($qr['valid_until'])) }}</small>
        </div>
        @endforeach
    </section>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Date</th>
                <th style="width: 11%;">Employee</th>
                <th style="width: 8%;">Position</th>
                <th style="width: 8%;">Branch</th>
                <th style="width: 9%;">Employee Code</th>
                <th style="width: 9%;">Morning In</th>
                <th style="width: 9%;">Morning Out</th>
                <th style="width: 9%;">Afternoon In</th>
                <th style="width: 9%;">Afternoon Out</th>
                <th style="width: 12%;">Overtime</th>
                <th style="width: 8%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($attendanceRecords as $att)
            <tr>
                <td>{{ date('M d, Y', strtotime($att['attendance_date'])) }}</td>
                <td>{{ htmlspecialchars($att['full_name']) }}</td>
                <td>{{ htmlspecialchars($att['position'] ?: '—') }}</td>
                <td>{{ htmlspecialchars($att['branch_name'] ?: '—') }}</td>
                <td>{{ htmlspecialchars($att['employee_code']) }}</td>
                <td>{{ $att['morning_time_in'] ? date('h:i A', strtotime($att['morning_time_in'])) : '-' }}</td>
                <td>{{ $att['morning_time_out'] ? date('h:i A', strtotime($att['morning_time_out'])) : '-' }}</td>
                <td>{{ $att['afternoon_time_in'] ? date('h:i A', strtotime($att['afternoon_time_in'])) : '-' }}</td>
                <td>{{ $att['afternoon_time_out'] ? date('h:i A', strtotime($att['afternoon_time_out'])) : '-' }}</td>
                <td>{{ !empty($att['overtime_time_in']) && !empty($att['overtime_time_out']) ? date('h:i A', strtotime($att['overtime_time_in'])) . ' - ' . date('h:i A', strtotime($att['overtime_time_out'])) . ' (' . (int) $att['overtime_minutes'] . ' min)' : '-' }}</td>
                <td>
                    @if ($att['status_morning'] === 'present' || $att['status_afternoon'] === 'present')
                        <span style="color: #16a34a; font-weight: 600;">Present</span>
                    @elseif ($att['status_morning'] === 'late' || $att['status_afternoon'] === 'late')
                        <span style="color: #d97706; font-weight: 600;">Late</span>
                    @else
                        <span style="color: #dc2626; font-weight: 600;">Absent</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @php
if (empty($attendanceRecords)) { echo '<tr><td colspan="11" style="text-align: center; color: #64748b; padding: 18px;">No attendance records found.</td></tr>'; }
@endphp
        </tbody>
    </table>

    <div class="footer">
        This report is system-generated by JFTECH WiFi Billing & Management System.
    </div>

    <script>
        @foreach ($attendanceQrCodes as $qr)
        new QRCode(document.getElementById('attendanceQr{{ ucfirst($qr['qr_type']) }}'), {
            text: @json($qr['qr_code']),
            width: 104,
            height: 104,
            colorDark: '#0a1628',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        @endforeach

        window.onload = function () { window.setTimeout(function () { window.print(); }, 100); };
    </script>
</body>
</html>

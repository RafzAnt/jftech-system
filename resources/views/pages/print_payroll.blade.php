<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {{ $record->employee_name }}</title>
    <style>
        @media print { *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } }
        @media print { .header { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; flex-wrap:nowrap; } .header .print-company-brand { min-width:0; } .header > :last-child { text-align:right !important; } }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; background: #f1f5f9; color: #0f172a; font: 14px/1.4 Arial, sans-serif; }
        .toolbar { max-width: 850px; margin: 0 auto 14px; text-align: right; }
        button { padding: 9px 16px; border: 0; border-radius: 6px; background: #1d4ed8; color: white; cursor: pointer; font-weight: 700; }
        .payslip { max-width: 850px; margin: auto; background: white; border: 1px solid #94a3b8; }
        .header { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 20px 24px; background: #1e3a8a; color: white; }
        .company { font-size: 19px; font-weight: 800; letter-spacing: .04em; }
        .muted { font-size: 12px; opacity: .9; }
        .print-company-brand { display: flex; align-items: flex-start; gap: 10px; }
        .print-company-logo { width: auto; height: 48px; object-fit: contain; }
        .print-company-brand .sub { font-size: 12px; opacity: .9; margin-top: 2px; }
        .body { padding: 22px 24px; }
        .section-title { margin: 18px 0 7px; padding: 7px 10px; background: #1e3a8a; color: white; font-weight: 700; text-transform: uppercase; font-size: 12px; }
        .meta, .amounts { width: 100%; border-collapse: collapse; }
        .meta td, .amounts td { padding: 8px 10px; border: 1px solid #cbd5e1; vertical-align: top; }
        .meta td { width: 25%; }
        .label { display: block; color: #64748b; font-size: 10px; text-transform: uppercase; margin-bottom: 3px; }
        .tables { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { padding: 6px 8px; border: 1px solid #cbd5e1; }
        .items th { background: #e2e8f0; text-align: left; font-size: 11px; }
        .items td:last-child, .items th:last-child { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; background: #f8fafc; }
        .net td { background: #16a34a; color: white; font-size: 16px; font-weight: 800; }
        .signatures { display: flex; justify-content: space-between; gap: 24px; margin-top: 28px; }
        .signature { width: 45%; padding-top: 8px; border-top: 1px solid #64748b; font-size: 12px; }
        @media (max-width: 650px) { .tables { grid-template-columns: 1fr; } .header, .signatures { flex-direction: column; } .signature { width: 100%; } .meta td { width: 50%; } }
        @media print { @page { size: A4 portrait; margin: 12mm; } body { padding: 0; background: white; } .toolbar { display: none; } .payslip { max-width: none; } thead { display: table-header-group; } tr { break-inside: avoid; page-break-inside: avoid; } th, td { overflow-wrap: anywhere; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    @php
        $money = fn ($value) => '₱' . number_format((float) $value, 2);
        $earnings = [
            'Basic Salary' => $record->basic_salary, 'Overtime Pay' => $record->overtime_pay,
            'Regular Holiday' => $record->regular_holiday, 'Special Holiday' => $record->special_holiday,
            'Night Differential' => $record->night_differential, 'Incentives' => $record->incentives,
            'Allowances' => $record->allowances, 'Leave Pay' => $record->leave_pay,
            'Rest Day Pay' => $record->rest_day_pay, 'Salary Adjustment' => $record->salary_adjustment,
            'Other Earnings' => $record->other_earnings,
        ];
        // Records made before the real payroll computation never deducted their late and undertime.
        $computed = !empty($record->computation_mode);
        $deductions = ($computed ? ['Late' . ($record->late_minutes ? ' (' . (int) $record->late_minutes . ' min)' : '') => $record->late, 'Undertime' . ($record->undertime_minutes ? ' (' . (int) $record->undertime_minutes . ' min)' : '') => $record->undertime] : []) + [
            'SSS' => $record->sss, 'PhilHealth' => $record->philhealth, 'Pag-IBIG' => $record->pagibig,
            'Withholding Tax' => $record->withholding_tax, 'Cash Advance' => $record->cash_advance,
            'Salary Loan' => $record->salary_loan, 'HDMF Loan' => $record->hdmf_loan,
            'Other Deductions' => $record->other_deductions,
        ];
    @endphp
    <main class="payslip">
        <header class="header">
            @include('partials.print_company')
            <div style="text-align:right"><strong>PAYSLIP</strong><br><span class="muted">{{ $record->payroll_period_start }} to {{ $record->payroll_period_end }}</span></div>
        </header>
        <div class="body">
            <table class="meta">
                <tr><td><span class="label">Employee Name</span><strong>{{ $record->employee_name }}</strong></td><td><span class="label">Employee Code</span><strong>{{ $record->employee_code }}</strong></td><td><span class="label">Position</span><strong>{{ $record->position ?: 'General Admin' }}</strong></td><td><span class="label">Status</span><strong>{{ ucfirst($record->employment_status) }}</strong></td></tr>
                <tr><td><span class="label">Department</span>{{ $record->department ?: 'N/A' }}</td><td><span class="label">Daily Rate</span>{{ $money($record->daily_rate) }}</td><td><span class="label">Hourly Rate</span>{{ $money($record->hourly_rate) }}</td><td><span class="label">Days Worked</span>{{ rtrim(rtrim(number_format((float) $record->total_days_worked, 2, '.', ''), '0'), '.') }}</td></tr>
            </table>
            <div class="section-title">Earnings and Deductions</div>
            <div class="tables">
                <table class="items"><thead><tr><th>Earnings</th><th>Amount</th></tr></thead><tbody>@foreach ($earnings as $label => $value)<tr><td>{{ $label }}</td><td>{{ $money($value) }}</td></tr>@endforeach<tr class="total"><td>Gross Pay</td><td>{{ $money($record->gross_pay) }}</td></tr></tbody></table>
                <table class="items"><thead><tr><th>Deductions</th><th>Amount</th></tr></thead><tbody>@foreach ($deductions as $label => $value)<tr><td>{{ $label }}</td><td>{{ $money($value) }}</td></tr>@endforeach<tr class="total"><td>Total Deductions</td><td>{{ $money($record->deductions) }}</td></tr></tbody></table>
            </div>
            <div class="section-title">Final Pay</div>
            <table class="amounts"><tr><td>Gross Pay</td><td style="text-align:right">{{ $money($record->gross_pay) }}</td></tr><tr><td>Total Deductions</td><td style="text-align:right">{{ $money($record->deductions) }}</td></tr><tr class="net"><td>NET PAY</td><td style="text-align:right">{{ $money($record->net_pay) }}</td></tr></table>
            <div class="signatures"><div class="signature">Approved By:<br><strong>Jieso B. Danao</strong><br>CEO | General Manager</div><div class="signature">Employee Signature:<br><br>____________________________</div></div>
        </div>
    </main>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>

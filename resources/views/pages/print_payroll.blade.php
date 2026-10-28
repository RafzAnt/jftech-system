@extends('layouts.print_report')
@use('App\Support\ReportFormat')

@php
    $reportTitle = 'Payslip';
    $documentTitle = 'Payslip - ' . $record->employee_name;
    $endOf = 'Payslip';
    $reportCoverage = ReportFormat::coverage($record->payroll_period_start, $record->payroll_period_end);
    $reportBranch = $record->employee_branch ? $record->employee_branch . ' Branch' : ReportFormat::branch();
    $orientation = 'portrait';

    // The employee acknowledges the payslip; the preparer and the approver are the same as on the other reports.
    $preparedBy = ReportFormat::preparedBy();
    $approver = config('company.approver');
    $signatories = [
        ['role' => 'Prepared by', 'name' => $preparedBy['name'], 'title' => $preparedBy['title']],
        ['role' => 'Approved by', 'name' => $approver['name'], 'title' => $approver['title']],
        ['role' => 'Received by', 'name' => $record->employee_name, 'title' => $record->position ?: 'Employee'],
    ];

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
    $computed = ! empty($record->computation_mode);
    $deductions = ($computed ? ['Late' . ($record->late_minutes ? ' (' . (int) $record->late_minutes . ' min)' : '') => $record->late, 'Undertime' . ($record->undertime_minutes ? ' (' . (int) $record->undertime_minutes . ' min)' : '') => $record->undertime] : []) + [
        'SSS' => $record->sss, 'PhilHealth' => $record->philhealth, 'Pag-IBIG' => $record->pagibig,
        'Withholding Tax' => $record->withholding_tax, 'Cash Advance' => $record->cash_advance,
        'Salary Loan' => $record->salary_loan, 'HDMF Loan' => $record->hdmf_loan,
        'Other Deductions' => $record->other_deductions,
    ];
@endphp

@section('content')
    <table class="detail-table">
        <tr>
            <td><span class="label">Employee Name</span><strong>{{ $record->employee_name }}</strong></td>
            <td><span class="label">Employee Code</span><strong>{{ $record->employee_code }}</strong></td>
            <td><span class="label">Position</span><strong>{{ $record->position ?: 'General Admin' }}</strong></td>
            <td><span class="label">Status</span><strong>{{ ucfirst($record->employment_status) }}</strong></td>
        </tr>
        <tr>
            <td><span class="label">Department</span>{{ $record->department ?: 'N/A' }}</td>
            <td><span class="label">Daily Rate</span>{{ $money($record->daily_rate) }}</td>
            <td><span class="label">Hourly Rate</span>{{ $money($record->hourly_rate) }}</td>
            <td><span class="label">Days Worked</span>{{ rtrim(rtrim(number_format((float) $record->total_days_worked, 2, '.', ''), '0'), '.') }}</td>
        </tr>
    </table>

    <div class="pair">
        <table class="report-table">
            <thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($earnings as $label => $value)
                    <tr><td>{{ $label }}</td><td class="num">{{ $money($value) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>GROSS PAY</td><td class="num">{{ $money($record->gross_pay) }}</td></tr></tfoot>
        </table>
        <table class="report-table">
            <thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($deductions as $label => $value)
                    <tr><td>{{ $label }}</td><td class="num">{{ $money($value) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>TOTAL DEDUCTIONS</td><td class="num">{{ $money($record->deductions) }}</td></tr></tfoot>
        </table>
    </div>

    <table class="report-table">
        <tbody>
            <tr><td>Gross Pay</td><td class="num">{{ $money($record->gross_pay) }}</td></tr>
            <tr><td>Less: Total Deductions</td><td class="num">{{ $money($record->deductions) }}</td></tr>
        </tbody>
        <tfoot class="net-pay"><tr><td>NET PAY</td><td class="num">{{ $money($record->net_pay) }}</td></tr></tfoot>
    </table>
@endsection

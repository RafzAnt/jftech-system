<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Payroll</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/payroll.css') }}?{{ $payrollCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Payroll</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Create, review, and print payroll records based on attendance</p>
            </div>
            <button id="generatePayrollButton" type="button" class="btn btn-success" onclick="document.getElementById('payrollModal').classList.add('active'); if (typeof window.openPayrollModal === 'function') window.openPayrollModal();">+ Generate Payroll</button>

        </div>

        @if ($success)
        <div class="alert alert-success">{{ htmlspecialchars($success) }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ htmlspecialchars($error) }}</div>
        @endif

        <div class="payroll-stats">
            <div class="stat-card">
                <div class="stat-value">{{ number_format($totalRecords) }}</div>
                <div class="stat-label">Payroll Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--accent);">₱{{ number_format($totalNetPay, 2) }}</div>
                <div class="stat-label">Total Net Pay</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--warning);">{{ number_format($processedCount) }}</div>
                <div class="stat-label">Processed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);">{{ number_format($paidCount) }}</div>
                <div class="stat-label">Paid</div>
            </div>
        </div>

        <div class="card print-hide" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Filter Payroll</span>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" class="form-input" {{ $hasEmployees ? '' : 'disabled' }}>
                                <option value="">All Employees</option>
                                @foreach ($employees as $employee)
                                <option value="{{ $employee['id'] }}" {{ (string) $selectedEmployee === (string) $employee['id'] ? 'selected' : '' }}>
                                    {{ htmlspecialchars($employee['full_name']) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ $selectedStatus === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="processed" {{ $selectedStatus === 'processed' ? 'selected' : '' }}>Processed</option>
                                <option value="paid" {{ $selectedStatus === 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Period Start</label>
                            <input type="date" name="period_start" class="form-input" value="{{ htmlspecialchars($selectedStart) }}">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Period End</label>
                            <input type="date" name="period_end" class="form-input" value="{{ htmlspecialchars($selectedEnd) }}">
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem; flex-wrap: wrap;">
                        <a href="payroll.php" class="btn btn-secondary">Clear</a>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span class="card-title">Payroll Records</span>
                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <div class="payslip-view-tabs">
                        <a href="payroll.php" class="payslip-view-tab {{ !$showArchived ? 'active' : '' }}">Active</a>
                        <a href="payroll.php?view=archived" class="payslip-view-tab {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="payslip-list">
                    @if (empty($payrollRecords))
                        <div class="card" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                            {{ $showArchived ? 'No archived payroll records.' : 'No payroll records found.' }}
                        </div>
                    @endif

                    @foreach ($payrollRecords as $record)
                    @php
                        $prDailyRate = (float) $record['daily_rate'];
                        $prDaysWorked = (float) $record['total_days_worked'];
                        $prMonthlyBasic = (float) ($record['monthly_basic_salary'] ?? $prDailyRate * 26);
                        $prHourlyRate = (float) ($record['hourly_rate'] ?? $prDailyRate / 8);
                        $prOtHours = (float) $record['overtime_hours'];
                        $prLate = (float) $record['late'];
                        $prUndertime = (float) $record['undertime'];
                        $prAbsent = (float) $record['absent'];
                        $prGross = (float) $record['gross_pay'];
                        $prDeductions = (float) $record['deductions'];
                        $prNet = (float) $record['net_pay'];
                        $prEmpStatus = $record['employment_status'] ?? 'Regular';
                    
@endphp
                        <div class="payslip-card">
                            <div class="payslip-header">
                                @include('partials.print_company')
                                <div class="payslip-period">
                                    <div><strong>PAYSLIP</strong>@if ($showArchived) <span class="payslip-archived-badge">ARCHIVED</span>@endif</div>
                                    <div>Payroll Period: {{ date('M d, Y', strtotime($record['payroll_period_start'])) }} - {{ date('M d, Y', strtotime($record['payroll_period_end'])) }}</div>
                                </div>
                            </div>

                            <div class="payslip-body">
                                <table class="payslip-meta-table">
                                    <tr>
                                        <td><span class="payslip-label">Employee Name</span><strong>{{ htmlspecialchars($record['employee_name']) }}</strong></td>
                                        <td><span class="payslip-label">Employee Code</span><strong>{{ htmlspecialchars($record['employee_code']) }}</strong></td>
                                        <td><span class="payslip-label">Position</span><strong>{{ htmlspecialchars($record['position'] ?: 'General Admin') }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><span class="payslip-label">Employment Status</span><strong>{{ htmlspecialchars(ucfirst($prEmpStatus)) }}</strong></td>
                                        <td><span class="payslip-label">Department</span><strong>{{ htmlspecialchars($record['department'] ?: 'N/A') }}</strong></td>
                                        <td><span class="payslip-label">Date</span><strong>{{ date('M d, Y', strtotime($record['created_at'] ?? $record['payroll_period_end'])) }}</strong></td>
                                    </tr>
                                </table>

                                <div class="payslip-section-title">Payroll Computation</div>
                                <table class="payslip-meta-table">
                                    <tr>
                                        <td><span class="payslip-label">Monthly Basic Salary</span><strong>₱{{ number_format($prMonthlyBasic, 2) }}</strong></td>
                                        <td><span class="payslip-label">Daily Rate</span><strong>₱{{ number_format($prDailyRate, 2) }}</strong></td>
                                        <td><span class="payslip-label">Hourly Rate</span><strong>₱{{ number_format($prHourlyRate, 2) }}</strong></td>
                                        <td><span class="payslip-label">Total Days Worked</span><strong>{{ number_format($prDaysWorked) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><span class="payslip-label">Overtime Hours</span><strong>{{ number_format($prOtHours, 2) }}</strong></td>
                                        <td><span class="payslip-label">Late</span><strong>{{ number_format($prLate, 2) }}</strong></td>
                                        <td><span class="payslip-label">Undertime</span><strong>{{ number_format($prUndertime, 2) }}</strong></td>
                                        <td><span class="payslip-label">Absent</span><strong>{{ number_format($prAbsent, 2) }}</strong></td>
                                    </tr>
                                </table>

                                <div class="payslip-tables">
                                    <div class="payslip-earnings">
                                        <div class="payslip-table-title">Earnings</div>
                                        <table class="payslip-table">
                                            <thead>
                                                <tr><th>Description</th><th>Amount</th></tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>Basic Salary</td><td>₱{{ number_format((float) $record['basic_salary'], 2) }}</td></tr>
                                                <tr><td>Overtime Pay</td><td>₱{{ number_format((float) $record['overtime_pay'], 2) }}</td></tr>
                                                <tr><td>Regular Holiday</td><td>₱{{ number_format((float) $record['regular_holiday'], 2) }}</td></tr>
                                                <tr><td>Special Holiday</td><td>₱{{ number_format((float) $record['special_holiday'], 2) }}</td></tr>
                                                <tr><td>Night Differential</td><td>₱{{ number_format((float) $record['night_differential'], 2) }}</td></tr>
                                                <tr><td>Incentives</td><td>₱{{ number_format((float) $record['incentives'], 2) }}</td></tr>
                                                <tr><td>Allowances</td><td>₱{{ number_format((float) $record['allowances'], 2) }}</td></tr>
                                                <tr><td>Leave Pay</td><td>₱{{ number_format((float) $record['leave_pay'], 2) }}</td></tr>
                                                <tr><td>Rest Day Pay</td><td>₱{{ number_format((float) $record['rest_day_pay'], 2) }}</td></tr>
                                                <tr><td>Salary Adjustment</td><td>₱{{ number_format((float) $record['salary_adjustment'], 2) }}</td></tr>
                                                <tr><td>Other Earnings</td><td>₱{{ number_format((float) $record['other_earnings'], 2) }}</td></tr>
                                                <tr class="total"><td>Total Gross Pay</td><td>₱{{ number_format($prGross, 2) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="payslip-deductions">
                                        <div class="payslip-table-title">Deductions</div>
                                        <table class="payslip-table">
                                            <thead>
                                                <tr><th>Description</th><th>Amount</th></tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>SSS</td><td>₱{{ number_format((float) $record['sss'], 2) }}</td></tr>
                                                <tr><td>PhilHealth</td><td>₱{{ number_format((float) $record['philhealth'], 2) }}</td></tr>
                                                <tr><td>Pag-IBIG</td><td>₱{{ number_format((float) $record['pagibig'], 2) }}</td></tr>
                                                <tr><td>Withholding Tax</td><td>₱{{ number_format((float) $record['withholding_tax'], 2) }}</td></tr>
                                                <tr><td>Cash Advance</td><td>₱{{ number_format((float) $record['cash_advance'], 2) }}</td></tr>
                                                <tr><td>Salary Loan</td><td>₱{{ number_format((float) $record['salary_loan'], 2) }}</td></tr>
                                                <tr><td>HDMF Loan</td><td>₱{{ number_format((float) $record['hdmf_loan'], 2) }}</td></tr>
                                                <tr><td>Other Deductions</td><td>₱{{ number_format((float) $record['other_deductions'], 2) }}</td></tr>
                                                <tr class="total"><td>Total Deductions</td><td>₱{{ number_format($prDeductions, 2) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <table class="payslip-summary">
                                    <tr><td>Gross Pay</td><td>₱{{ number_format($prGross, 2) }}</td></tr>
                                    <tr><td>Total Deductions</td><td>₱{{ number_format($prDeductions, 2) }}</td></tr>
                                    <tr class="netpay"><td>Net Pay</td><td>₱{{ number_format($prNet, 2) }}</td></tr>
                                </table>

                                <div class="payslip-confirm">
                                    I confirm that the above payroll computation is correct and I have received the net amount stated herein.
                                </div>

                                <div class="payslip-signatures">
                                    <div>Approved By:<br><strong>Jieso B. Danao</strong><br>CEO | General Manager</div>
                                    <div>Employee Signature:<br><br>_______________________________</div>
                                </div>

                                <div class="payslip-actions">
                                    @if ($showArchived)
                                        <a href="?restore={{ $record['id'] }}" class="btn btn-sm btn-success">Restore</a>
                                        <a href="?purge={{ $record['id'] }}" class="btn btn-sm btn-danger" onclick="return confirm('Permanently delete this archived payroll record? This cannot be undone.');">Delete Permanently</a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="editPayroll({{ htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8') }})">Edit</button>
                                        <a href="?delete={{ $record['id'] }}" class="btn btn-sm btn-danger" onclick="return confirm('Archive this payroll record?');">Archive</a>
                                    @endif
                                    <a href="{{ url('/pages/print_payroll.php?id=' . $record['id']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">Print</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="payrollModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="payrollModalTitle">Generate Payroll</h3>
                </div>
                <button class="modal-close" onclick="document.getElementById('payrollModal').classList.remove('active'); if (typeof window.closePayrollModal === 'function') window.closePayrollModal();">&times;</button>
            </div>
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="payroll_id" id="payrollId">

                <div class="form-section">
                    <div class="form-section-title">Employee &amp; Period</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" id="employeeId" class="form-input" required onchange="syncDailyRate(); syncAttendance();">
                                <option value="">Select employee</option>
                                @foreach ($employees as $employee)
                                <option
                                    value="{{ $employee['id'] }}"
                                    data-rate="{{ htmlspecialchars($employee['daily_rate']) }}"
                                >
                                    {{ htmlspecialchars($employee['full_name'] . ' - ' . $employee['employee_code']) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Daily Rate (₱) *</label>
                            <input type="number" name="daily_rate" id="dailyRate" class="form-input calc-readonly" step="0.01" min="0" required readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Period Start *</label>
                            <input type="date" name="payroll_period_start" id="periodStart" class="form-input" required onchange="syncAttendance();">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Period End *</label>
                            <input type="date" name="payroll_period_end" id="periodEnd" class="form-input" required onchange="syncAttendance();">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Days Worked (auto)</label>
                            <input type="number" name="total_days_worked" id="totalDaysWorked" class="form-input" min="0" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="payrollStatus" class="form-input">
                                <option value="draft">Draft</option>
                                <option value="processed">Processed</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Payroll Computation</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Monthly Basic Salary</label>
                            <input type="text" id="monthlyBasic" class="form-input calc-readonly" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hourly Rate</label>
                            <input type="text" id="hourlyRate" class="form-input calc-readonly" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Overtime Hours</label>
                            <input type="number" name="overtime_hours" id="overtime_hours" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Late Deduction (auto)</label>
                            <input type="number" name="late" id="late" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Undertime</label>
                            <input type="number" name="undertime" id="undertime" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Absent Deduction (auto)</label>
                            <input type="number" name="absent" id="absent" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Earnings</div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Basic Salary (auto)</label><input type="number" name="basic_salary" id="basic_salary" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly></div>
                        <div class="form-group"><label class="form-label">Overtime Pay</label><input type="number" name="overtime_pay" id="overtime_pay" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Regular Holiday</label><input type="number" name="regular_holiday" id="regular_holiday" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Special Holiday</label><input type="number" name="special_holiday" id="special_holiday" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Night Differential</label><input type="number" name="night_differential" id="night_differential" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Incentives</label><input type="number" name="incentives" id="incentives" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Allowances</label><input type="number" name="allowances" id="allowances" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Leave Pay</label><input type="number" name="leave_pay" id="leave_pay" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Rest Day Pay</label><input type="number" name="rest_day_pay" id="rest_day_pay" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Salary Adjustment</label><input type="number" name="salary_adjustment" id="salary_adjustment" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Other Earnings</label><input type="number" name="other_earnings" id="other_earnings" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Deductions</div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">SSS (auto)</label><input type="number" name="sss" id="sss" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly></div>
                        <div class="form-group"><label class="form-label">PhilHealth (auto)</label><input type="number" name="philhealth" id="philhealth" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly></div>
                        <div class="form-group"><label class="form-label">Pag-IBIG / HDMF (auto)</label><input type="number" name="pagibig" id="pagibig" class="form-input calc-readonly" step="0.01" min="0" value="0" readonly></div>
                        <div class="form-group"><label class="form-label">Withholding Tax</label><input type="number" name="withholding_tax" id="withholding_tax" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Cash Advance</label><input type="number" name="cash_advance" id="cash_advance" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Salary Loan</label><input type="number" name="salary_loan" id="salary_loan" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">HDMF Loan</label><input type="number" name="hdmf_loan" id="hdmf_loan" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                        <div class="form-group"><label class="form-label">Other Deductions</label><input type="number" name="other_deductions" id="other_deductions" class="form-input" step="0.01" min="0" value="0" oninput="computePayroll();"></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Summary</div>
                    <div class="computed-grid">
                        <div class="form-group"><label class="form-label">Gross Pay</label><input type="text" id="grossPay" class="form-input calc-readonly" readonly></div>
                        <div class="form-group"><label class="form-label">Total Deductions</label><input type="text" id="totalDeductions" class="form-input calc-readonly" readonly></div>
                        <div class="form-group"><label class="form-label">Net Pay</label><input type="text" id="netPay" class="form-input calc-readonly" readonly style="color: var(--success);"></div>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 1rem;">
                    Daily rate, attendance, late/absent deductions, and SSS, PhilHealth, and Pag-IBIG are calculated automatically. Configure the weekly statutory amounts in Business Settings.
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('payrollModal').classList.remove('active'); if (typeof window.closePayrollModal === 'function') window.closePayrollModal();">Cancel</button>
                    <button type="submit" name="save_payroll" class="btn btn-success">Save Payroll</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const payrollEmployees = {{ $employeeJson }};

    const EARNING_FIELDS = ['basic_salary', 'overtime_pay', 'regular_holiday', 'special_holiday', 'night_differential', 'incentives', 'allowances', 'leave_pay', 'rest_day_pay', 'salary_adjustment', 'other_earnings'];
    const DEDUCTION_FIELDS = ['sss', 'philhealth', 'pagibig', 'withholding_tax', 'cash_advance', 'salary_loan', 'hdmf_loan', 'other_deductions'];
    const NUM_FIELDS = ['overtime_hours', 'late', 'undertime', 'absent'].concat(EARNING_FIELDS, DEDUCTION_FIELDS);

    function setVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

    function resetPayrollForm() {
        document.getElementById('payrollModalTitle').textContent = 'Generate Payroll';
        document.getElementById('payrollId').value = '';
        document.getElementById('employeeId').value = '';
        document.getElementById('dailyRate').value = '';
        const today = new Date();
        const mondayOffset = (today.getDay() + 6) % 7;
        const monday = new Date(today);
        monday.setDate(today.getDate() - mondayOffset);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        const dateValue = function(date) { return date.toISOString().slice(0, 10); };
        document.getElementById('periodStart').value = dateValue(monday);
        document.getElementById('periodEnd').value = dateValue(sunday);
        document.getElementById('totalDaysWorked').value = '';
        document.getElementById('payrollStatus').value = 'draft';
        NUM_FIELDS.forEach(function(id) { setVal(id, '0'); });
        computePayroll();
    }

    function openPayrollModal() {
        resetPayrollForm();
        const modal = document.getElementById('payrollModal');
        if (modal) {
            modal.classList.add('active');
            if (window.openModal) {
                openModal('payrollModal');
            }
        }
    }

    function closePayrollModal() {
        const modal = document.getElementById('payrollModal');
        if (modal) {
            modal.classList.remove('active');
            if (window.closeModal) {
                closeModal('payrollModal');
            }
        }
    }

    function syncDailyRate() {
        const employeeId = document.getElementById('employeeId').value;
        const employee = payrollEmployees.find(function(item) {
            return String(item.id) === String(employeeId);
        });

        if (employee) {
            document.getElementById('dailyRate').value = employee.daily_rate;
        }
        syncAttendance();
    }

    function computePayroll() {
        const num = function(id) { return parseFloat(document.getElementById(id).value) || 0; };
        const dailyRate = num('dailyRate');
        const monthlyBasic = dailyRate * 26;
        const hourlyRate = dailyRate / 8;

        setVal('monthlyBasic', '₱' + monthlyBasic.toFixed(2));
        setVal('hourlyRate', '₱' + hourlyRate.toFixed(2));

        let gross = 0;
        EARNING_FIELDS.forEach(function(id) { gross += num(id); });
        let totalDeductions = 0;
        DEDUCTION_FIELDS.forEach(function(id) { totalDeductions += num(id); });
        const net = Math.max(0, gross - totalDeductions);

        setVal('grossPay', '₱' + gross.toFixed(2));
        setVal('totalDeductions', '₱' + totalDeductions.toFixed(2));
        setVal('netPay', '₱' + net.toFixed(2));
    }

    function syncAttendance() {
        const employeeId = document.getElementById('employeeId').value;
        const start = document.getElementById('periodStart').value;
        const end = document.getElementById('periodEnd').value;
        if (!employeeId || !start || !end) return;

        fetch('payroll.php?action=attendance&employee_id=' + encodeURIComponent(employeeId) + '&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                const days = data.days_worked || 0;
                setVal('totalDaysWorked', days);
                const rate = parseFloat(document.getElementById('dailyRate').value) || 0;
                setVal('basic_salary', (days * rate).toFixed(2));
                setVal('late', Number(data.late_amount || 0).toFixed(2));
                setVal('absent', Number(data.absent_amount || 0).toFixed(2));
                setVal('sss', Number(data.sss || 0).toFixed(2));
                setVal('philhealth', Number(data.philhealth || 0).toFixed(2));
                setVal('pagibig', Number(data.pagibig || 0).toFixed(2));
                computePayroll();
            })
            .catch(function() {});
    }

    function editPayroll(record) {
        document.getElementById('payrollModalTitle').textContent = 'Edit Payroll';
        document.getElementById('payrollId').value = record.id;
        document.getElementById('employeeId').value = record.employee_id;
        document.getElementById('dailyRate').value = record.daily_rate;
        document.getElementById('periodStart').value = record.payroll_period_start;
        document.getElementById('periodEnd').value = record.payroll_period_end;
        document.getElementById('totalDaysWorked').value = record.total_days_worked;
        document.getElementById('payrollStatus').value = record.status;
        NUM_FIELDS.forEach(function(id) {
            setVal(id, record[id] !== undefined ? record[id] : '0');
        });
        computePayroll();
        document.getElementById('payrollModal').classList.add('active');
    }

    function printPayslip(record) {
        const printLogoUrl = @json(url('/assets/jftech-logo.png'));
        const rv = function(key) { return Number(record[key]) || 0; };
        const dailyRate = rv('daily_rate');
        const gross = rv('gross_pay');
        const deductions = rv('deductions');
        const net = rv('net_pay');
        const monthlyBasic = rv('monthly_basic_salary') || dailyRate * 26;
        const hourlyRate = rv('hourly_rate') || dailyRate / 8;
        const empStatus = (record.employment_status || 'Regular').toString().replace(/^./, c => c.toUpperCase());
        const money = n => '₱' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const employmentDate = record.created_at ? record.created_at : record.payroll_period_end;

        const earningRows = [
            ['Basic Salary', rv('basic_salary')],
            ['Overtime Pay', rv('overtime_pay')],
            ['Regular Holiday', rv('regular_holiday')],
            ['Special Holiday', rv('special_holiday')],
            ['Night Differential', rv('night_differential')],
            ['Incentives', rv('incentives')],
            ['Allowances', rv('allowances')],
            ['Leave Pay', rv('leave_pay')],
            ['Rest Day Pay', rv('rest_day_pay')],
            ['Salary Adjustment', rv('salary_adjustment')],
            ['Other Earnings', rv('other_earnings')]
        ];
        const deductionRows = [
            ['SSS', rv('sss')],
            ['PhilHealth', rv('philhealth')],
            ['Pag-IBIG', rv('pagibig')],
            ['Withholding Tax', rv('withholding_tax')],
            ['Cash Advance', rv('cash_advance')],
            ['Salary Loan', rv('salary_loan')],
            ['HDMF Loan', rv('hdmf_loan')],
            ['Other Deductions', rv('other_deductions')]
        ];
        const earningHtml = earningRows.map(function(r) {
            return '<tr><td>' + r[0] + '</td><td>' + money(r[1]) + '</td></tr>';
        }).join('') + '<tr class="total"><td>Total Gross Pay</td><td>' + money(gross) + '</td></tr>';
        const deductionHtml = deductionRows.map(function(r) {
            return '<tr><td>' + r[0] + '</td><td>' + money(r[1]) + '</td></tr>';
        }).join('') + '<tr class="total"><td>Total Deductions</td><td>' + money(deductions) + '</td></tr>';

        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Payslip - ${record.employee_name}</title>
                <style>
                    * { box-sizing: border-box; }
                    body { font-family: Arial, Helvetica, sans-serif; margin: 1rem; color: #111827; background: #fff; }
                    .print-card { max-width: 820px; margin: auto; border: 1px solid #000; }
                    .print-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; background: #1e3a8a; color: #fff; padding: 1.25rem 1.5rem; }
                    .print-header .company { font-size: 1.2rem; font-weight: 800; letter-spacing: 0.04em; }
                    .print-header .address { font-size: 0.8rem; opacity: 0.9; margin-top: 0.25rem; }
                    .print-company-brand { display: flex; align-items: flex-start; gap: 10px; }
                    .print-company-logo { width: auto; height: 48px; object-fit: contain; }
                    .print-company-brand .sub { font-size: 0.8rem; opacity: 0.9; margin-top: 0.25rem; }
                    .print-header .meta { text-align: right; font-size: 0.85rem; line-height: 1.5; }
                    .print-header .meta strong { font-size: 1rem; letter-spacing: 0.08em; }
                    .print-body { padding: 1.25rem 1.5rem; }
                    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
                    .meta-table td { border: 1px solid #cbd5e1; padding: 0.5rem 0.75rem; vertical-align: top; width: 33.333%; }
                    .meta-table .lbl { display: block; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; margin-bottom: 0.15rem; }
                    .meta-table strong { font-size: 0.95rem; color: #0f172a; }
                    .section-title { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; background: #1e3a8a; color: #fff; padding: 0.45rem 0.75rem; margin-bottom: 0.5rem; }
                    .table-title { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; background: #e2e8f0; color: #1e293b; padding: 0.45rem 0.75rem; margin-bottom: 0.5rem; }
                    .print-tables { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
                    .p-table { width: 100%; border-collapse: collapse; }
                    .p-table th { background: #f1f5f9; text-align: left; font-size: 0.72rem; text-transform: uppercase; padding: 0.4rem 0.6rem; border: 1px solid #cbd5e1; color: #334155; }
                    .p-table td { padding: 0.4rem 0.6rem; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #0f172a; }
                    .p-table td:last-child, .p-table th:last-child { text-align: right; white-space: nowrap; }
                    .p-table tr.total td { font-weight: 700; background: #f8fafc; }
                    .summary { width: 100%; max-width: 380px; margin-left: auto; border-collapse: collapse; }
                    .summary td { border: 1px solid #cbd5e1; padding: 0.5rem 0.75rem; font-size: 0.9rem; }
                    .summary td:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
                    .summary tr.netpay td { background: #16a34a; color: #fff; font-weight: 800; font-size: 1.05rem; }
                    .confirm { font-size: 0.8rem; color: #475569; border-top: 1px dashed #cbd5e1; padding-top: 0.75rem; margin-top: 0.5rem; }
                    .signatures { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
                    .signatures > div { width: 45%; min-width: 200px; border-top: 1px solid #94a3b8; padding-top: 0.5rem; font-size: 0.82rem; color: #334155; }
                    @media print {
                        *, *::before, *::after { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                        body { margin: 0; }
                        .print-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; flex-wrap: nowrap; }
                        .print-header .print-company-brand { min-width: 0; }
                        .print-card { border-color: #000; }
                    }
                    @media (max-width: 650px) { .print-tables { grid-template-columns: 1fr; } .meta-table td { width: 50%; } .signatures > div { width: 100%; } .print-header { flex-direction: column; gap: 0.5rem; } .print-header .meta { text-align: right; } }
                </style>
            </head>
            <body>
                <div class="print-card">
                    <div class="print-header">
                        <div class="print-company-brand">
                            <img class="print-company-logo" src="${printLogoUrl}" alt="JFTECH IT Solutions logo">
                            <div>
                            <div class="company">JFTECH IT SOLUTIONS &mdash; MAIN OFFICE</div>
                            <div class="address">Del Carmen Sur, Balilihan, Bohol<br>jftechitsolutions@gmail.com</div>
                            </div>
                        </div>
                        <div class="meta">
                            <div><strong>PAYSLIP</strong></div>
                            <div>Period: ${record.payroll_period_start} to ${record.payroll_period_end}</div>
                        </div>
                    </div>
                    <div class="print-body">
                        <table class="meta-table">
                            <tr>
                                <td><span class="lbl">Employee Name</span><strong>${record.employee_name}</strong></td>
                                <td><span class="lbl">Employee Code</span><strong>${record.employee_code}</strong></td>
                                <td><span class="lbl">Position</span><strong>${record.position || 'General Admin'}</strong></td>
                            </tr>
                            <tr>
                                <td><span class="lbl">Employment Status</span><strong>${empStatus}</strong></td>
                                <td><span class="lbl">Department</span><strong>${record.department || 'N/A'}</strong></td>
                                <td><span class="lbl">Date</span><strong>${employmentDate}</strong></td>
                            </tr>
                        </table>

                        <div class="section-title">Payroll Computation</div>
                        <table class="meta-table">
                            <tr>
                                <td><span class="lbl">Monthly Basic Salary</span><strong>${money(monthlyBasic)}</strong></td>
                                <td><span class="lbl">Daily Rate</span><strong>${money(dailyRate)}</strong></td>
                                <td><span class="lbl">Hourly Rate</span><strong>${money(hourlyRate)}</strong></td>
                                <td><span class="lbl">Total Days Worked</span><strong>${Number(record.total_days_worked)}</strong></td>
                            </tr>
                            <tr>
                                <td><span class="lbl">Overtime Hours</span><strong>${Number(record.overtime_hours || 0).toFixed(2)}</strong></td>
                                <td><span class="lbl">Late</span><strong>${Number(record.late || 0).toFixed(2)}</strong></td>
                                <td><span class="lbl">Undertime</span><strong>${Number(record.undertime || 0).toFixed(2)}</strong></td>
                                <td><span class="lbl">Absent</span><strong>${Number(record.absent || 0).toFixed(2)}</strong></td>
                            </tr>
                        </table>

                        <div class="print-tables">
                            <div>
                                <div class="table-title">Earnings</div>
                                <table class="p-table">
                                    <thead><tr><th>Description</th><th>Amount</th></tr></thead>
                                    <tbody>${earningHtml}</tbody>
                                </table>
                            </div>
                            <div>
                                <div class="table-title">Deductions</div>
                                <table class="p-table">
                                    <thead><tr><th>Description</th><th>Amount</th></tr></thead>
                                    <tbody>${deductionHtml}</tbody>
                                </table>
                            </div>
                        </div>

                        <table class="summary">
                            <tr><td>Gross Pay</td><td>${money(gross)}</td></tr>
                            <tr><td>Total Deductions</td><td>${money(deductions)}</td></tr>
                            <tr class="netpay"><td>Net Pay</td><td>${money(net)}</td></tr>
                        </table>

                        <div class="confirm">
                            I confirm that the above payroll computation is correct and I have received the net amount stated herein.
                        </div>

                        <div class="signatures">
                            <div>Approved By:<br><strong>Jieso B. Danao</strong><br>CEO | General Manager</div>
                            <div>Employee Signature:<br><br>_______________________________</div>
                        </div>
                    </div>
                </div>
                <script>window.print();<\/script>
            </body>
            </html>
        `;

        const win = window.open('', '_blank');
        if (win) {
            win.document.write(html);
            win.document.close();
        } else {
            alert('Please allow popups to print the payslip.');
        }
    }

    function printPayrollRecords() {
        document.body.classList.add('printing-payroll-records');
        window.print();
    }

    // Expose modal actions explicitly for the buttons' inline click handlers.
    window.openPayrollModal = openPayrollModal;
    window.closePayrollModal = closePayrollModal;
    window.printPayrollRecords = printPayrollRecords;

    window.addEventListener('afterprint', function () {
        document.body.classList.remove('printing-payroll-records');
    });

    document.addEventListener('DOMContentLoaded', function() {
        const generatePayrollButton = document.getElementById('generatePayrollButton');
        if (generatePayrollButton) {
            generatePayrollButton.addEventListener('click', openPayrollModal);
        }
    });
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

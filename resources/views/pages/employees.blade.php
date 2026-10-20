<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Employees</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/employees.css') }}?{{ $employeesCssVersion }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Employees</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Manage employees and attendance</p>
            </div>
            <button type="button" class="btn btn-success" id="addEmployeeAction" data-tab-action="detailsTab" onclick="openEmployeeModal()" @if ($activeTab !== 'details') style="display: none;" @endif>+ Add Employee</button>
            <button type="button" class="btn btn-success" id="manualAttendanceAction" data-tab-action="attendanceTab" onclick="openManualAttendance()" @if ($activeTab !== 'attendance') style="display: none;" @endif>+ Manual Attendance</button>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab-btn {{ $activeTab === 'details' ? 'active' : '' }}" data-tab="detailsTab">Details</button>
                <button class="tab-btn {{ $activeTab === 'attendance' ? 'active' : '' }}" data-tab="attendanceTab">Attendance</button>
            </div>

            <!-- D-1: Details Tab -->
            <div id="detailsTab" class="tab-content {{ $activeTab === 'details' ? 'active' : '' }}">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Employee List <span class="badge badge-info">{{ count($listedEmployees) }}{{ $selectedListBranch ? ' · ' . $selectedListBranch : '' }}</span></span>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                <input type="hidden" name="tab" value="details">
                                <select name="branch" class="form-select" style="width: auto; min-width: 150px; padding: 0.5rem;" aria-label="Branch" onchange="this.form.submit()">
                                    <option value="">{{ \App\Support\BranchScope::isRestricted() ? 'My branch' : 'All Branches' }}</option>
                                    @foreach ($employeeBranches as $branch)
                                    <option value="{{ $branch }}" {{ $selectedListBranch === $branch ? 'selected' : '' }}>{{ $branch }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="search-box" style="margin: 0;">
                                <input type="text" class="search-input" placeholder="Search employees..." data-target="#employeesTable">
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container">
                            <table class="data-table" id="employeesTable">
                                <thead>
                                    <tr>
                                        <th>Employee Code</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Branch</th>
                                        <th>Mobile Number</th>
                                        <th>Daily Rate</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($listedEmployees as $emp)
                                    <tr>
                                        <td><strong>{{ $emp['employee_code'] }}</strong></td>
                                        <td>{{ htmlspecialchars($emp['full_name']) }}</td>
                                        <td>{{ $emp['position'] ?: '—' }}</td>
                                        <td><span class="badge badge-info">{{ $emp['branch_name'] ?: '—' }}</span></td>
                                        <td>{{ $emp['contact_number'] }}</td>
                                        <td style="color: var(--success); font-weight: 600;">₱{{ number_format($emp['daily_rate'], 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $emp['status'] === 'active' ? 'success' : ($emp['status'] === 'inactive' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($emp['status']) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if (\App\Support\BranchScope::canManageEmployee($emp['position']))
                                            <button class="btn btn-sm btn-primary btn-icon" data-emp='@json($emp)' onclick="editEmployeeFromData(this)">Edit</button>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    @if (empty($listedEmployees))
                                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">No employees found</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- D-2: Attendance Tab -->
            <div id="attendanceTab" class="tab-content {{ $activeTab === 'attendance' ? 'active' : '' }}">
                <!-- QR Code Section -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <span class="card-title">QR Code for Attendance</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge badge-info">Scan with Employee App</span>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="printAttendanceQrSheet()">Print QR Sheet</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <!-- Morning QR -->
                            <div class="qr-section">
                                <h3>Morning Session</h3>
                                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ formatScheduleTime($qrMorningStart) }} - {{ formatScheduleTime($qrMorningEnd) }}</p>
                                 @php
$morningQR = null;
                                 foreach ($qrCodes as $qr) {
                                     if ($qr['qr_type'] === 'morning' && $qr['status'] === 'active') {
                                         $morningQR = $qr;
                                         break;
                                     }
                                 }
                                 
@endphp
                                @if ($morningQR && $morningQR['status'] === 'active')
                                <div id="morningQR" class="qr-display"></div>
                                <p style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">Code: {{ $morningQR['qr_code'] }}</p>
                                @else
                                <p style="color: var(--warning);">No active QR code</p>
                                @endif
                                {{-- One QR code serves every branch, so only the super admin can replace it. --}}
                                @if ($canGenerateQr)
                                <form method="POST" action="?tab=attendance" style="margin-top: 0.75rem;">
            @csrf
                                    <input type="hidden" name="qr_type" value="morning">
                                    <button type="submit" name="generate_qr" class="btn btn-primary btn-sm">Generate Morning QR</button>
                                </form>
                                @endif
                            </div>
                            <!-- Afternoon QR -->
                            <div class="qr-section">
                                <h3>Afternoon Session</h3>
                                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ formatScheduleTime($qrAfternoonStart) }} - {{ formatScheduleTime($qrAfternoonEnd) }}</p>
                                 @php
$afternoonQR = null;
                                 foreach ($qrCodes as $qr) {
                                     if ($qr['qr_type'] === 'afternoon' && $qr['status'] === 'active') {
                                         $afternoonQR = $qr;
                                         break;
                                     }
                                 }
                                 
@endphp
                                @if ($afternoonQR && $afternoonQR['status'] === 'active')
                                <div id="afternoonQR" class="qr-display"></div>
                                <p style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">Code: {{ $afternoonQR['qr_code'] }}</p>
                                @else
                                <p style="color: var(--warning);">No active QR code</p>
                                @endif
                                {{-- One QR code serves every branch, so only the super admin can replace it. --}}
                                @if ($canGenerateQr)
                                <form method="POST" action="?tab=attendance" style="margin-top: 0.75rem;">
            @csrf
                                    <input type="hidden" name="qr_type" value="afternoon">
                                    <button type="submit" name="generate_qr" class="btn btn-primary btn-sm">Generate Afternoon QR</button>
                                </form>
                                @endif
                </div>
                            <!-- Overtime QR -->
                            <div class="qr-section">
                                <h3>Overtime</h3>
                                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ formatScheduleTime($qrOvertimeStart) }} - {{ formatScheduleTime($qrOvertimeEnd) }}</p>
                                 @php
$overtimeQR = null;
                                 foreach ($qrCodes as $qr) {
                                     if ($qr['qr_type'] === 'overtime' && $qr['status'] === 'active') {
                                         $overtimeQR = $qr;
                                         break;
                                     }
                                 }
                                 
@endphp
                                @if ($overtimeQR)
                                <div id="overtimeQR" class="qr-display"></div>
                                <p style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">Code: {{ $overtimeQR['qr_code'] }}</p>
                                @else
                                <p style="color: var(--warning);">No active QR code</p>
                                @endif
                                {{-- One QR code serves every branch, so only the super admin can replace it. --}}
                                @if ($canGenerateQr)
                                <form method="POST" action="?tab=attendance" style="margin-top: 0.75rem;">
            @csrf
                                    <input type="hidden" name="qr_type" value="overtime">
                                    <button type="submit" name="generate_qr" class="btn btn-primary btn-sm">Generate Overtime QR</button>
                                </form>
                                @endif
                            </div>
            </div>

            <section class="attendance-qr-print-sheet" id="attendanceQrPrintSheet" aria-hidden="true">
                <header class="attendance-print-header">
                    @include('partials.print_company')
                    <span>ATTENDANCE QR CODES</span>
                </header>
                <div class="attendance-print-qr attendance-print-time-in">
                    <h2>Morning Session</h2>
                    <div class="attendance-print-qr-code" id="printMorningQr"></div>
                    <p>{{ formatScheduleTime($qrMorningStart) }} - {{ formatScheduleTime($qrMorningEnd) }}</p>
                    <p class="attendance-print-qr-value">Code: {{ $morningQR['qr_code'] ?? 'No active QR code' }}</p>
                </div>
                <div class="attendance-print-cut-line"></div>
                <div class="attendance-print-qr attendance-print-time-out">
                    <h2>Afternoon Session</h2>
                    <div class="attendance-print-qr-code" id="printAfternoonQr"></div>
                    <p>{{ formatScheduleTime($qrAfternoonStart) }} - {{ formatScheduleTime($qrAfternoonEnd) }}</p>
                    <p class="attendance-print-qr-value">Code: {{ $afternoonQR['qr_code'] ?? 'No active QR code' }}</p>
                </div>
                <div class="attendance-print-cut-line"></div>
                <div class="attendance-print-qr attendance-print-time-out">
                    <h2>Overtime</h2>
                    <div class="attendance-print-qr-code" id="printOvertimeQr"></div>
                    <p>{{ formatScheduleTime($qrOvertimeStart) }} - {{ formatScheduleTime($qrOvertimeEnd) }}</p>
                    <p class="attendance-print-qr-value">Code: {{ $overtimeQR['qr_code'] ?? 'No active QR code' }}</p>
                </div>
            </section>
        </div>
                </div>

                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span class="card-title">Attendance Records</span>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                <input type="hidden" name="tab" value="attendance">
                                <select name="employee_id" class="form-select" style="width: auto; min-width: 190px; padding: 0.5rem;">
                                    <option value="">All Employees</option>
                                    @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}" {{ (string) $selectedEmployeeId === (string) $employee['id'] ? 'selected' : '' }}>{{ htmlspecialchars($employee['full_name']) }}</option>
                                    @endforeach
                                </select>
                                <select name="branch" class="form-select" style="width: auto; min-width: 150px; padding: 0.5rem;">
                                    <option value="">All Branches</option>
                                    @foreach ($employeeBranches as $branch)
                                    <option value="{{ $branch }}" {{ $selectedAttendanceBranch === $branch ? 'selected' : '' }}>{{ $branch }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="attendance_date" class="form-input" value="{{ $selectedDate }}" style="width: auto; padding: 0.5rem;">
                                <button type="submit" class="btn btn-sm btn-primary">View</button>
                            </form>
                            <a class="btn btn-sm btn-secondary" href="{{ url('/pages/print_attendance.php?start=' . urlencode($monthStart) . '&end=' . urlencode($monthEnd) . ($selectedEmployeeId !== '' ? '&employee_id=' . urlencode($selectedEmployeeId) : '') . ($selectedAttendanceBranch !== '' ? '&branch=' . urlencode($selectedAttendanceBranch) : '')) }}" target="_blank" rel="noopener">Print / Export</a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Position</th>
                                        <th>Branch</th>
                                        <th>Date</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Overtime</th>
                                        <th>Hours</th>
                                        <th>Morning Status</th>
                                        <th>Afternoon Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($attendanceRecords as $record)
                                    <tr>
                                        <td><strong>{{ htmlspecialchars($record['employee_name']) }}</strong>@if (($record['qr_code_used'] ?? '') === 'manual') <span class="badge badge-secondary" title="{{ $record['notes'] ?? 'Manual entry' }}">Manual</span>@endif</td>
                                        <td>{{ $record['position'] ?: '—' }}</td>
                                        <td>{{ $record['branch_name'] ?: '—' }}</td>
                                        <td>{{ $record['attendance_date'] ? date('M d, Y', strtotime($record['attendance_date'])) : date('M d, Y', strtotime($selectedDate)) }}</td>
                                        <td class="attendance-in">{{ $record['morning_time_in'] ? date('h:i A', strtotime($record['morning_time_in'])) : '-' }}</td>
                                        <td class="attendance-out">{{ $record['morning_time_out'] ? date('h:i A', strtotime($record['morning_time_out'])) : '-' }}</td>
                                        <td class="attendance-in">{{ $record['afternoon_time_in'] ? date('h:i A', strtotime($record['afternoon_time_in'])) : '-' }}</td>
                                        <td class="attendance-out">{{ $record['afternoon_time_out'] ? date('h:i A', strtotime($record['afternoon_time_out'])) : '-' }}</td>
                                        <td>
                                            @if (!empty($record['overtime_time_in']) && !empty($record['overtime_time_out']))
                                                {{ date('h:i A', strtotime($record['overtime_time_in'])) }} - {{ date('h:i A', strtotime($record['overtime_time_out'])) }} <span class="badge badge-info">{{ (int) $record['overtime_minutes'] }} min</span>
                                            @elseif (!empty($record['overtime_time_in']))
                                                {{ date('h:i A', strtotime($record['overtime_time_in'])) }} - <span style="color: var(--warning);">ongoing</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) ($record['total_hours'] ?? 0), 2) }}h</td>
                                        <td>
                                            @if ($record['status_morning'] === 'present')
                                                <span class="badge badge-success">Present</span>
                                            @elseif ($record['status_morning'] === 'late')
                                                <span class="badge badge-warning">Late ({{ $record['late_minutes_morning'] ?? 0 }} min)</span>
                                            @elseif ($record['status_morning'] === 'absent')
                                                <span class="badge badge-danger">Absent</span>
                                            @else
                                                <span style="color: var(--text-muted);">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($record['status_afternoon'] === 'present')
                                                <span class="badge badge-success">Present</span>
                                            @elseif ($record['status_afternoon'] === 'late')
                                                <span class="badge badge-warning">Late ({{ $record['late_minutes_afternoon'] ?? 0 }} min)</span>
                                            @elseif ($record['status_afternoon'] === 'absent')
                                                <span class="badge badge-danger">Absent</span>
                                            @else
                                                <span style="color: var(--text-muted);">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    @if (empty($attendanceRecords))
                                    <tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:2rem;">No attendance records for {{ $attendanceView === 'date' ? date('M d, Y', strtotime($selectedDate)) : date('F Y', strtotime($selectedMonth . '-01')) }}</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Manual Attendance Modal -->
    @php($manualOld = fn ($key, $default = '') => !empty($manualAttendanceFailed) ? (string) request()->input($key, $default) : $default)
    <div class="modal-overlay" id="manualAttendanceModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Manual Attendance</h3>
                </div>
                <button class="modal-close" type="button" onclick="closeModal('manualAttendanceModal')">&times;</button>
            </div>
            <form method="POST" action="?tab=attendance">
                @csrf
                <p style="margin: 0 0 1rem; color: var(--text-muted); font-size: 0.85rem;">Record attendance by hand when an employee could not scan the QR code. Saving a day that already has a record updates the sessions you fill in. "Auto" sets Present or Late from the time in.@if (\App\Support\BranchScope::isRestricted()) Attendance for Admin employees is recorded by the super admin.@endif</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="manualEmployee">Employee *</label>
                        <select name="manual_employee_id" id="manualEmployee" class="form-select" required>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                            @if ($employee['status'] === 'active' && \App\Support\BranchScope::canManageEmployee($employee['position']))
                            <option value="{{ $employee['id'] }}" {{ $manualOld('manual_employee_id', (string) $selectedEmployeeId) === (string) $employee['id'] ? 'selected' : '' }}>{{ htmlspecialchars($employee['full_name']) }} — {{ $employee['branch_name'] }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="manualDate">Date *</label>
                        <input type="date" name="manual_date" id="manualDate" class="form-input" value="{{ $manualOld('manual_date', $selectedDate) }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                @foreach (['morning' => 'Morning', 'afternoon' => 'Afternoon'] as $key => $label)
                <div class="manual-session" data-session="{{ $key }}">
                    <div class="manual-session__title">{{ $label }} session <small>{{ formatScheduleTime($key === 'morning' ? $qrMorningStart : $qrAfternoonStart) }} - {{ formatScheduleTime($key === 'morning' ? $qrMorningEnd : $qrAfternoonEnd) }}</small></div>
                    <div class="form-grid manual-session__grid">
                        <div class="form-group">
                            <label class="form-label" for="manual_{{ $key }}_status">Status</label>
                            <select name="manual_{{ $key }}_status" id="manual_{{ $key }}_status" class="form-select manual-status">
                                @foreach (['' => 'Auto', 'present' => 'Present', 'late' => 'Late', 'absent' => 'Absent'] as $value => $text)
                                <option value="{{ $value }}" {{ $manualOld("manual_{$key}_status") === (string) $value ? 'selected' : '' }}>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="manual_{{ $key }}_in">Time in</label>
                            <input type="time" name="manual_{{ $key }}_in" id="manual_{{ $key }}_in" class="form-input" value="{{ $manualOld("manual_{$key}_in") }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="manual_{{ $key }}_out">Time out</label>
                            <input type="time" name="manual_{{ $key }}_out" id="manual_{{ $key }}_out" class="form-input" value="{{ $manualOld("manual_{$key}_out") }}">
                        </div>
                    </div>
                </div>
                @endforeach
                <div class="manual-session" data-session="overtime">
                    <div class="manual-session__title">Overtime <small>after the afternoon session; the minutes go to the payroll</small></div>
                    <div class="form-grid manual-session__grid">
                        <div class="form-group">
                            <label class="form-label" for="manual_overtime_in">Overtime in</label>
                            <input type="time" name="manual_overtime_in" id="manual_overtime_in" class="form-input" value="{{ $manualOld('manual_overtime_in') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="manual_overtime_out">Overtime out</label>
                            <input type="time" name="manual_overtime_out" id="manual_overtime_out" class="form-input" value="{{ $manualOld('manual_overtime_out') }}">
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;">
                            <label style="display:flex;align-items:center;gap:.5rem;"><input type="checkbox" name="manual_overtime_clear" value="1" {{ $manualOld('manual_overtime_clear') === '1' ? 'checked' : '' }}> No overtime (remove it)</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="manualRemarks">Reason / remarks <span style="color: var(--text-muted);">(Optional)</span></label>
                    <textarea name="manual_remarks" id="manualRemarks" class="form-input" rows="2" maxlength="300" placeholder="e.g. Phone battery died, scanned late in person">{{ $manualOld('manual_remarks') }}</textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('manualAttendanceModal')">Cancel</button>
                    <button type="submit" name="manual_attendance" value="1" class="btn btn-success">Save Attendance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Modal (Add/Edit) -->
    <div class="modal-overlay" id="employeeModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title" id="modalTitle">Add Employee</h3>
                </div>
                <button class="modal-close" onclick="closeModal('employeeModal')">&times;</button>
            </div>
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="employee_id" id="employeeId">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" id="empFirstName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name <span style="color: var(--text-muted);">(Optional)</span></label>
                        <input type="text" name="middle_name" class="form-input" id="empMiddleName">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" id="empLastName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position *</label>
                        <select name="position" class="form-select" id="empPosition" required onchange="syncPositionRate()">
                            @foreach ($selectablePositions as $position)
                            <option value="{{ $position }}">{{ $position }}</option>
                            @endforeach
                        </select>
                        <small style="color: var(--text-muted);">{{ $canAssignAdminPosition ? 'The super admin adds Admin employees only. Branch admins add the technicians of their branch.' : 'You add the technicians of your branch. The super admin adds Admin employees.' }}</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="empBranch">Branch *</label>
                        {{-- Type the branch and pick from the suggestions (the list comes from the datalist). --}}
                        <input type="text" name="branch_name" class="form-input" id="empBranch" list="empBranchSuggestions" placeholder="Type a branch name" autocomplete="off" required>
                        <datalist id="empBranchSuggestions">
                            @foreach ($employeeBranches as $branch)
                            <option value="{{ $branch }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="contact_number" class="form-input" id="empContact" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" id="empEmail" placeholder="employee@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Rate (₱)</label>
                        <input type="number" name="daily_rate" class="form-input" id="empDailyRate" step="0.01" readonly aria-describedby="dailyRateHelp">
                        <small id="dailyRateHelp" style="color: var(--text-muted);">Set automatically from the selected position.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Hired</label>
                        <input type="date" name="date_hired" class="form-input" id="empDateHired">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" id="empAddress" rows="2" placeholder="Full address..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('employeeModal')">Cancel</button>
                    <button type="submit" name="save_employee" class="btn btn-success">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Generate QR codes
    @if ($morningQR)
    new QRCode(document.getElementById("morningQR"), {
        text: "{{ $morningQR['qr_code'] }}",
        width: 180,
        height: 180,
        colorDark: "#0a1628",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    @endif

    @if ($afternoonQR)
    new QRCode(document.getElementById("afternoonQR"), {
        text: "{{ $afternoonQR['qr_code'] }}",
        width: 180,
        height: 180,
        colorDark: "#0a1628",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    @endif

    @if ($overtimeQR)
    new QRCode(document.getElementById("overtimeQR"), {
        text: "{{ $overtimeQR['qr_code'] }}",
        width: 180,
        height: 180,
        colorDark: "#0a1628",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    @endif

    // The header button follows the active tab: Add Employee on Details, Manual Attendance on Attendance.
    document.querySelectorAll('.tab-btn').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-tab-action]').forEach(action => {
            action.style.display = action.dataset.tabAction === tab.dataset.tab ? '' : 'none';
        });
    }));

    function openManualAttendance() {
        syncManualSessions();
        openModal('manualAttendanceModal');
    }

    // Absent sessions have no clock times, so lock those fields.
    function syncManualSessions() {
        document.querySelectorAll('.manual-session').forEach(block => {
            const status = block.querySelector('.manual-status');
            if (!status) return; // the overtime block has no status
            const absent = status.value === 'absent';
            block.querySelectorAll('input[type="time"]').forEach(input => {
                input.disabled = absent;
                if (absent) input.value = '';
            });
        });
    }
    document.querySelectorAll('.manual-status').forEach(select => select.addEventListener('change', syncManualSessions));
    @if (!empty($manualAttendanceFailed))
    document.addEventListener('DOMContentLoaded', openManualAttendance);
    @endif

    function openEmployeeModal() {
        document.getElementById('modalTitle').textContent = 'Add Employee';
        document.getElementById('employeeId').value = '';
        document.getElementById('empFirstName').value = '';
        document.getElementById('empMiddleName').value = '';
        document.getElementById('empLastName').value = '';
        // Drop a position that an earlier edit added just to show an existing employee, then start from the first choice.
        const addPositionSelect = document.getElementById('empPosition');
        addPositionSelect.querySelectorAll('option[data-temporary]').forEach(option => option.remove());
        addPositionSelect.selectedIndex = 0;
        setEmployeeBranch('');
        document.getElementById('empContact').value = '';
        document.getElementById('empEmail').value = '';
        syncPositionRate();
        document.getElementById('empDateHired').value = '';
        document.getElementById('empAddress').value = '';
        openModal('employeeModal');
    }

    // Edit employee
    function editEmployeeFromData(button) {
        try {
            const raw = button.getAttribute('data-emp') || button.dataset.emp;
            const emp = raw ? JSON.parse(raw) : null;
            if (emp) return editEmployee(emp);
        } catch (e) {
            console.error('Failed to parse employee data', e);
        }
    }

    function editEmployee(emp) {
        document.getElementById('modalTitle').textContent = 'Edit Employee';
        document.getElementById('employeeId').value = emp.id;
        const nameParts = String(emp.full_name || '').trim().split(/\s+/);
        document.getElementById('empFirstName').value = nameParts.shift() || '';
        document.getElementById('empLastName').value = nameParts.length ? nameParts.pop() : '';
        document.getElementById('empMiddleName').value = nameParts.join(' ');
        const positionSelect = document.getElementById('empPosition');
        positionSelect.querySelectorAll('option[data-temporary]').forEach(option => option.remove());
        // Each role is only offered some positions, but an employee keeps showing the position they already have.
        if (emp.position && payrollPositionRates[emp.position] && ![...positionSelect.options].some(option => option.value === emp.position)) {
            const kept = new Option(emp.position, emp.position);
            kept.dataset.temporary = '1';
            positionSelect.add(kept);
        }
        positionSelect.value = payrollPositionRates[emp.position] ? emp.position : '';
        if (positionSelect.selectedIndex < 0) positionSelect.selectedIndex = 0;
        setEmployeeBranch(emp.branch_name || '');
        document.getElementById('empContact').value = emp.contact_number;
        document.getElementById('empEmail').value = emp.email;
        syncPositionRate();
        document.getElementById('empDateHired').value = emp.date_hired;
        document.getElementById('empAddress').value = emp.address;
        openModal('employeeModal');
    }

    const payrollPositionRates = @json($employeePositionRates);

    // A branch admin only has their own branch, so it is filled in and locked; the super admin types one.
    const employeeBranchNames = @json($employeeBranches);
    const lockedEmployeeBranch = @json($scopeBranch !== null ? ($employeeBranches[0] ?? '') : '');
    function setEmployeeBranch(name) {
        const input = document.getElementById('empBranch');
        input.value = lockedEmployeeBranch || name;
        input.readOnly = lockedEmployeeBranch !== '';
        validateEmployeeBranch();
    }
    function validateEmployeeBranch() {
        const input = document.getElementById('empBranch');
        const typed = input.value.trim().toLocaleLowerCase();
        const known = employeeBranchNames.some(name => name.toLocaleLowerCase() === typed);
        input.setCustomValidity(typed === '' || known ? '' : 'Choose a branch from the suggestions.');
    }
    document.getElementById('empBranch').addEventListener('input', validateEmployeeBranch);
    document.getElementById('empBranch').addEventListener('change', validateEmployeeBranch);

    function syncPositionRate() {
        const position = document.getElementById('empPosition').value;
        const rate = Number(payrollPositionRates[position] || 0);
        document.getElementById('empDailyRate').value = rate.toFixed(2);
    }

    function printAttendanceQrSheet() {
        const qrDisplays = [
            ['morningQR', 'printMorningQr'],
            ['afternoonQR', 'printAfternoonQr'],
            ['overtimeQR', 'printOvertimeQr'],
        ];

        qrDisplays.forEach(([sourceId, targetId]) => {
            const source = document.getElementById(sourceId);
            const target = document.getElementById(targetId);
            target.replaceChildren();

            const sourceImage = source?.querySelector('img');
            const sourceCanvas = source?.querySelector('canvas');
            const imageSource = sourceImage?.currentSrc || sourceImage?.src || (sourceCanvas ? sourceCanvas.toDataURL('image/png') : '');

            if (!imageSource) {
                target.textContent = 'No active QR code';
                return;
            }

            const image = new Image();
            image.src = imageSource;
            image.alt = 'Attendance QR code';
            target.appendChild(image);
        });

        document.body.classList.add('printing-attendance-qr');
        window.setTimeout(() => window.print(), 50);
    }

    window.addEventListener('afterprint', () => document.body.classList.remove('printing-attendance-qr'));
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

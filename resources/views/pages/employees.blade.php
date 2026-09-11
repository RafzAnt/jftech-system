<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Employees</title>
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
            <button class="btn btn-success" onclick="openEmployeeModal()">+ Add Employee</button>
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
                        <span class="card-title">Employee List</span>
                        <div class="search-box" style="margin: 0;">
                            <input type="text" class="search-input" placeholder="Search employees..." data-target="#employeesTable">
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container">
                            <table class="data-table" id="employeesTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Contact</th>
                                        <th>Daily Rate</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employees as $emp)
                                    <tr>
                                        <td><strong>{{ $emp['employee_code'] }}</strong></td>
                                        <td>{{ htmlspecialchars($emp['full_name']) }}</td>
                                        <td>{{ $emp['position'] ?: '—' }}</td>
                                        <td>{{ $emp['contact_number'] }}</td>
                                        <td style="color: var(--success); font-weight: 600;">PHP {{ number_format($emp['daily_rate'], 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $emp['status'] === 'active' ? 'success' : ($emp['status'] === 'inactive' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($emp['status']) }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-icon" data-emp='@json($emp)' onclick="editEmployeeFromData(this)">Edit</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @if (empty($employees))
                                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No employees found</td></tr>
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
                                <form method="POST" action="?tab=attendance" style="margin-top: 0.75rem;">
            @csrf
                                    <input type="hidden" name="qr_type" value="morning">
                                    <button type="submit" name="generate_qr" class="btn btn-primary btn-sm">Generate Morning QR</button>
                                </form>
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
                                <form method="POST" action="?tab=attendance" style="margin-top: 0.75rem;">
            @csrf
                                    <input type="hidden" name="qr_type" value="afternoon">
                                    <button type="submit" name="generate_qr" class="btn btn-primary btn-sm">Generate Afternoon QR</button>
                                </form>
                </div>
            </div>

            <section class="attendance-qr-print-sheet" id="attendanceQrPrintSheet" aria-hidden="true">
                <header class="attendance-print-header">
                    @include('partials.print_company')
                    <span>ATTENDANCE QR CODES</span>
                </header>
                <div class="attendance-print-qr attendance-print-time-in">
                    <h2>TIME IN</h2>
                    <div class="attendance-print-qr-code" id="printMorningQr"></div>
                    <p>{{ formatScheduleTime($qrMorningStart) }} - {{ formatScheduleTime($qrMorningEnd) }}</p>
                </div>
                <div class="attendance-print-cut-line"></div>
                <div class="attendance-print-qr attendance-print-time-out">
                    <h2>TIME OUT</h2>
                    <div class="attendance-print-qr-code" id="printAfternoonQr"></div>
                    <p>{{ formatScheduleTime($qrAfternoonStart) }} - {{ formatScheduleTime($qrAfternoonEnd) }}</p>
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
                                <input type="date" name="attendance_date" class="form-input" value="{{ $selectedDate }}" style="width: auto; padding: 0.5rem;">
                                <button type="submit" class="btn btn-sm btn-primary">View</button>
                            </form>
                            <a class="btn btn-sm btn-secondary" href="{{ url('/pages/print_attendance.php?start=' . urlencode($monthStart) . '&end=' . urlencode($monthEnd) . ($selectedEmployeeId !== '' ? '&employee_id=' . urlencode($selectedEmployeeId) : '')) }}" target="_blank" rel="noopener">Print / Export</a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="data-table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Position</th>
                                        <th>Date</th>
                                        <th>Morning In</th>
                                        <th>Morning Out</th>
                                        <th>Afternoon In</th>
                                        <th>Afternoon Out</th>
                                        <th>Hours</th>
                                        <th>Morning Status</th>
                                        <th>Afternoon Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($attendanceRecords as $record)
                                    <tr>
                                        <td><strong>{{ htmlspecialchars($record['employee_name']) }}</strong></td>
                                        <td>{{ $record['position'] ?: '—' }}</td>
                                        <td>{{ $record['attendance_date'] ? date('M d, Y', strtotime($record['attendance_date'])) : '-' }}</td>
                                        <td class="attendance-in">{{ $record['morning_time_in'] ? date('h:i A', strtotime($record['morning_time_in'])) : '-' }}</td>
                                        <td class="attendance-out">{{ $record['morning_time_out'] ? date('h:i A', strtotime($record['morning_time_out'])) : '-' }}</td>
                                        <td class="attendance-in">{{ $record['afternoon_time_in'] ? date('h:i A', strtotime($record['afternoon_time_in'])) : '-' }}</td>
                                        <td class="attendance-out">{{ $record['afternoon_time_out'] ? date('h:i A', strtotime($record['afternoon_time_out'])) : '-' }}</td>
                                        <td>{{ $record['total_hours'] }}h</td>
                                        <td>
                                            @if ($record['status_morning'] === 'present')
                                                <span class="badge badge-success">Present</span>
                                            @elseif ($record['status_morning'] === 'late')
                                                <span class="badge badge-warning">Late ({{ $record['late_minutes_morning'] ?? 0 }} min)</span>
                                            @else
                                                <span class="badge badge-danger">Absent</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($record['status_afternoon'] === 'present')
                                                <span class="badge badge-success">Present</span>
                                            @elseif ($record['status_afternoon'] === 'late')
                                                <span class="badge badge-warning">Late ({{ $record['late_minutes_afternoon'] ?? 0 }} min)</span>
                                            @else
                                                <span class="badge badge-danger">Absent</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                    @if (empty($attendanceRecords))
                                    <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">No attendance records for {{ $attendanceView === 'date' ? date('M d, Y', strtotime($selectedDate)) : date('F Y', strtotime($selectedMonth . '-01')) }}</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

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
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-input" id="empFullName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select" id="empPosition">
                            @foreach ($employeePositions ?? ['Technician', 'Technician I', 'Technician II', 'Master Technician', 'Intern'] as $position)
                            <option value="{{ $position }}">{{ $position }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-input" id="empContact" placeholder="09123456789">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" id="empEmail" placeholder="employee@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Rate (PHP)</label>
                        <input type="number" name="daily_rate" class="form-input" id="empDailyRate" placeholder="500.00" step="0.01">
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

    function openEmployeeModal() {
        document.getElementById('modalTitle').textContent = 'Add Employee';
        document.getElementById('employeeId').value = '';
        document.getElementById('empFullName').value = '';
        document.getElementById('empPosition').value = 'Technician';
        document.getElementById('empContact').value = '';
        document.getElementById('empEmail').value = '';
        document.getElementById('empDailyRate').value = '';
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
        document.getElementById('empFullName').value = emp.full_name;
        document.getElementById('empPosition').value = emp.position || 'Technician';
        document.getElementById('empContact').value = emp.contact_number;
        document.getElementById('empEmail').value = emp.email;
        document.getElementById('empDailyRate').value = emp.daily_rate;
        document.getElementById('empDateHired').value = emp.date_hired;
        document.getElementById('empAddress').value = emp.address;
        openModal('employeeModal');
    }

    function printAttendanceQrSheet() {
        const pairs = [
            ['morningQR', 'printMorningQr'],
            ['afternoonQR', 'printAfternoonQr'],
        ];

        pairs.forEach(([sourceId, targetId]) => {
            const source = document.getElementById(sourceId);
            const target = document.getElementById(targetId);
            target.replaceChildren();
            if (source?.firstElementChild) target.appendChild(source.firstElementChild.cloneNode(true));
        });

        document.body.classList.add('printing-attendance-qr');
        window.print();
    }

    window.addEventListener('afterprint', () => document.body.classList.remove('printing-attendance-qr'));
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>

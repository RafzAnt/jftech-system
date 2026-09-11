<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Employees Module (D) - Details, Attendance, Payroll
 * Features: QR Code for attendance scanning
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$employeesCssVersion = filemtime(public_path('css/employees.css'));

$activeTab = $request->query('tab') ?? 'details';
$success = '';
$error = '';

function buildUniqueUsername(PDO $pdo, string $fullName, ?int $ignoreUserId = null): string {
    $base = strtolower(preg_replace('/[^a-z0-9]+/', '', $fullName));
    if ($base === '') {
        $base = 'employee';
    }

    $username = $base;
    $suffix = 1;

    while (true) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $params = [$username];

        if ($ignoreUserId !== null) {
            $sql .= " AND id != ?";
            $params[] = $ignoreUserId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $username;
        }

        $suffix++;
        $username = $base . $suffix;
    }
}

function userUsernameExists(PDO $pdo, string $username, ?int $ignoreUserId = null): bool {
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];

    if ($ignoreUserId !== null) {
        $sql .= " AND id != ?";
        $params[] = $ignoreUserId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function generateTemporaryPassword(): string {
    return 'JFTECH' . random_int(1000, 9999);
}

function formatScheduleTime(string $time): string {
    $parsed = strtotime($time);
    return $parsed !== false ? date('g:i A', $parsed) : $time;
}

$employeePositions = ['Technician', 'Technician I', 'Technician II', 'Master Technician', 'Intern'];

// Get all employees
$stmt = $pdo->query("SELECT e.*, u.username, u.status as user_status FROM employees e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.full_name");
$employees = $stmt->fetchAll();

// Get the selected date or month attendance view.
$attendanceView = 'date';
$selectedDate = $request->query('attendance_date') ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}
$selectedMonth = $request->query('attendance_month') ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$selectedEmployeeId = $request->query('employee_id') ?? '';
$monthStart = $attendanceView === 'date' ? $selectedDate : date('Y-m-01', strtotime($selectedMonth . '-01'));
$monthEnd = $attendanceView === 'date' ? $selectedDate : date('Y-m-t', strtotime($selectedMonth . '-01'));

// Auto-mark absent employees for past sessions
function autoMarkAbsent($pdo, $selectedDate) {
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('morning_deadline', 'afternoon_deadline')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $morningDeadline = $settings['morning_deadline'] ?? '08:30:00';
    $afternoonDeadline = $settings['afternoon_deadline'] ?? '13:30:00';
    
    $stmt = $pdo->query("SELECT id FROM employees WHERE status = 'active'");
    $employeeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($selectedDate === $today) {
        if (strcmp($currentTime, $morningDeadline) > 0) {
            foreach ($employeeIds as $empId) {
                $stmt = $pdo->prepare("SELECT id, morning_time_in, morning_time_out, status_morning FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmt->execute([$empId, $today]);
                $att = $stmt->fetch();
                
                $noMorningScan = $att && (is_null($att['morning_time_in']) || $att['morning_time_in'] === '') && (is_null($att['morning_time_out']) || $att['morning_time_out'] === '');
                
                if (!$att) {
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status_morning) VALUES (?, ?, 'absent')");
                    $stmt->execute([$empId, $today]);
                } elseif ($noMorningScan && (empty($att['status_morning']) || $att['status_morning'] === '')) {
                    $stmt = $pdo->prepare("UPDATE attendance SET status_morning = 'absent' WHERE id = ?");
                    $stmt->execute([$att['id']]);
                }
            }
        }
        
        if (strcmp($currentTime, $afternoonDeadline) > 0) {
            foreach ($employeeIds as $empId) {
                $stmt = $pdo->prepare("SELECT id, afternoon_time_in, afternoon_time_out, status_afternoon FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmt->execute([$empId, $today]);
                $att = $stmt->fetch();
                
                if (!$att) {
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status_afternoon) VALUES (?, ?, 'absent')");
                    $stmt->execute([$empId, $today]);
                } else {
                    $noAfternoonScan = (is_null($att['afternoon_time_in']) || $att['afternoon_time_in'] === '') && (is_null($att['afternoon_time_out']) || $att['afternoon_time_out'] === '');
                    if ($noAfternoonScan && (empty($att['status_afternoon']) || $att['status_afternoon'] === '')) {
                        $stmt = $pdo->prepare("UPDATE attendance SET status_afternoon = 'absent' WHERE id = ?");
                        $stmt->execute([$att['id']]);
                    }
                }
            }
        }
    }
}

// Run auto-mark absent when the current month is being viewed.
if (($attendanceView === 'date' && $selectedDate === date('Y-m-d')) || ($attendanceView === 'month' && $selectedMonth === date('Y-m'))) {
    autoMarkAbsent($pdo, $selectedDate);
}

// Show only actual attendance records for the selected month.
$attendanceQuery = "SELECT e.id as employee_id, e.full_name as employee_name, e.position, a.id as attendance_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.afternoon_time_in, a.afternoon_time_out, a.status_morning, a.status_afternoon, a.total_hours FROM employees e JOIN attendance a ON e.id = a.employee_id WHERE e.status = 'active' AND a.attendance_date BETWEEN ? AND ?";
$attendanceParams = [$monthStart, $monthEnd];
if ($selectedEmployeeId !== '') {
    $attendanceQuery .= " AND e.id = ?";
    $attendanceParams[] = $selectedEmployeeId;
}
$attendanceQuery .= " ORDER BY e.full_name";
$stmt = $pdo->prepare($attendanceQuery);
$stmt->execute($attendanceParams);
$attendanceRecords = $stmt->fetchAll();

// Get today's attendance count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE attendance_date = CURDATE()");
$stmt->execute();
$todayAttendanceCount = $stmt->fetch()['total'];

// Get late threshold for late minutes calculation
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_threshold_minutes'");
$lateThresholdMinutes = (int) ($stmt->fetch()['setting_value'] ?? 30);

// Calculate late minutes for each attendance record
foreach ($attendanceRecords as &$record) {
    $record['late_minutes_morning'] = null;
    $record['late_minutes_afternoon'] = null;

    if ($record['status_morning'] === 'late' && $record['morning_time_in']) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_morning_start'");
        $morningStart = $stmt->fetch()['setting_value'] ?? '08:00:00';
        $startTime = strtotime($morningStart);
        $scanTime = strtotime($record['morning_time_in']);
        if ($scanTime !== false && $startTime !== false) {
            $diffMinutes = (int) (($scanTime - $startTime) / 60);
            $record['late_minutes_morning'] = max(0, $diffMinutes);
        }
    }

    if ($record['status_afternoon'] === 'late' && $record['afternoon_time_in']) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_afternoon_start'");
        $afternoonStart = $stmt->fetch()['setting_value'] ?? '13:00:00';
        $startTime = strtotime($afternoonStart);
        $scanTime = strtotime($record['afternoon_time_in']);
        if ($scanTime !== false && $startTime !== false) {
            $diffMinutes = (int) (($scanTime - $startTime) / 60);
            $record['late_minutes_afternoon'] = max(0, $diffMinutes);
        }
    }
}
unset($record);

// Get attendance schedule settings for QR display and generation
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('qr_morning_start', 'qr_morning_end', 'qr_afternoon_start', 'qr_afternoon_end')");
$attendanceSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$qrMorningStart = $attendanceSettings['qr_morning_start'] ?? '08:00:00';
$qrMorningEnd = $attendanceSettings['qr_morning_end'] ?? '12:00:00';
$qrAfternoonStart = $attendanceSettings['qr_afternoon_start'] ?? '13:00:00';
$qrAfternoonEnd = $attendanceSettings['qr_afternoon_end'] ?? '17:00:00';

// Get QR codes
$stmt = $pdo->query("SELECT * FROM qr_codes WHERE valid_date = CURDATE() ORDER BY created_at DESC LIMIT 2");
$qrCodes = $stmt->fetchAll();

// Generate QR Code if requested
if ($request->isMethod('post') && $request->has('generate_qr')) {
    try {
        $qrType = $request->input('qr_type');
        $validDate = date('Y-m-d');
        
        // Get time settings from database
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_" . $qrType . "_start'");
        $startTime = $stmt->fetch()['setting_value'];
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_" . $qrType . "_end'");
        $endTime = $stmt->fetch()['setting_value'];
        
        // Deactivate old QR codes of same type
        $stmt = $pdo->prepare("UPDATE qr_codes SET status = 'expired' WHERE qr_type = ? AND valid_date = ?");
        $stmt->execute([$qrType, $validDate]);
        
        // Generate new QR code
        $qrCode = 'JFTECH-' . strtoupper($qrType) . '-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
        
        $stmt = $pdo->prepare("INSERT INTO qr_codes (qr_code, qr_type, valid_date, valid_from, valid_until, status, created_by) VALUES (?, ?, ?, ?, ?, 'active', ?)");
        $stmt->execute([$qrCode, $qrType, $validDate, $startTime, $endTime, auth()->id()]);
        
        $success = ucfirst($qrType) . ' QR Code generated successfully!';
        
        // Refresh QR codes
        $stmt = $pdo->query("SELECT * FROM qr_codes WHERE valid_date = CURDATE() ORDER BY created_at DESC LIMIT 2");
        $qrCodes = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Add/Edit Employee
if ($request->isMethod('post') && $request->has('save_employee')) {
    try {
        $employeeId = $request->input('employee_id') ?? null;
        $fullName = trim($request->input('full_name'));
        $position = trim((string) $request->input('position'));
        $contactNumber = trim($request->input('contact_number'));
        $email = trim($request->input('email'));
        $address = trim($request->input('address'));
        $dateHired = $request->input('date_hired');
        $dailyRate = $request->input('daily_rate');

        if ($fullName === '') {
            throw new Exception('Full name is required.');
        }
        if (! in_array($position, $employeePositions, true)) {
            throw new Exception('Select a valid employee position.');
        }

        $pdo->beginTransaction();

        if ($employeeId) {
            $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, position = ?, department = NULL, contact_number = ?, email = ?, address = ?, date_hired = ?, daily_rate = ? WHERE id = ?");
            $stmt->execute([$fullName, $position, $contactNumber, $email, $address, $dateHired, $dailyRate, $employeeId]);
            $pdo->commit();
            $success = 'Employee updated successfully! User accounts are managed in User Management.';
        } else {
            $employeeCode = 'EMP-' . date('Y') . '-' . strtoupper(uniqid());
            $stmt = $pdo->prepare("INSERT INTO employees (employee_code, user_id, full_name, position, department, contact_number, email, address, date_hired, daily_rate, status) VALUES (?, NULL, ?, ?, NULL, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$employeeCode, $fullName, $position, $contactNumber, $email, $address, $dateHired, $dailyRate]);
            
            $pdo->commit();
            $success = 'Employee added successfully. Create and link an app account from User Management when needed.';
        }

        // Refresh employees list
        $stmt = $pdo->query("SELECT e.*, u.username, u.status as user_status FROM employees e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.full_name");
        $employees = $stmt->fetchAll();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Mark employee absent for a session
if ($request->isMethod('post') && $request->has('mark_absent')) {
    try {
        $employeeId = $request->input('employee_id');
        $session = $request->input('session'); // 'morning' or 'afternoon'
        $markDate = $request->input('attendance_date') ?? date('Y-m-d');

        // Check if attendance record exists
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$employeeId, $markDate]);
        $attendance = $stmt->fetch();

        if ($attendance) {
            // Update existing record
            if ($session === 'morning') {
                $stmt = $pdo->prepare("UPDATE attendance SET status_morning = 'absent' WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE attendance SET status_afternoon = 'absent' WHERE id = ?");
            }
            $stmt->execute([$attendance['id']]);
        } else {
            // Create new record with absent status
            if ($session === 'morning') {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status_morning) VALUES (?, ?, 'absent')");
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status_afternoon) VALUES (?, ?, 'absent')");
            }
            $stmt->execute([$employeeId, $markDate]);
        }

        $success = 'Employee marked as absent for ' . $session . ' session!';

        // Refresh attendance records
        $stmt = $pdo->prepare("SELECT a.*, e.full_name as employee_name, e.position FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.attendance_date = ? ORDER BY a.morning_time_in DESC");
        $stmt->execute([$selectedDate]);
        $attendanceRecords = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Payroll data
$stmt = $pdo->query("SELECT p.*, e.full_name as employee_name, e.position FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.created_at DESC LIMIT 20");
$payrollRecords = $stmt->fetchAll();
    return get_defined_vars();
};

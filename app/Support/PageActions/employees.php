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
$clientFilterCssVersion = filemtime(public_path('css/client_filter.css'));

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

function employeeFullName(string $firstName, string $middleName, string $lastName): string {
    return implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
}

function formatScheduleTime(string $time): string {
    $parsed = strtotime($time);
    return $parsed !== false ? date('g:i A', $parsed) : $time;
}

// Employee roles and rates are standardized for the technician workforce.
// The server assigns the rate instead of trusting a browser-submitted value.
$employeePositionRates = [
    'Technician I' => 500.00,
    'Technician II' => 580.00,
    'Admin' => 600.00,
];
$employeePositions = array_keys($employeePositionRates);
// The super admin only adds Admin employees (who then get a branch admin account in User Accounts);
// the branch admins add the technicians of their own branch.
$canAssignAdminPosition = ! \App\Support\BranchScope::isRestricted();
$selectablePositions = $canAssignAdminPosition ? ['Admin'] : array_values(array_diff($employeePositions, ['Admin']));

// Branches come from the same list the Clients module uses.
$employeeBranches = array_keys(config('bohol_branches', []));

// A branch admin only works with their own branch's employees. The QR code is shared by every branch,
// so only the super admin may generate a new one.
$scopeBranch = \App\Support\BranchScope::current();
if ($scopeBranch !== null) {
    $employeeBranches = array_values(array_filter($employeeBranches, static fn (string $name): bool => strcasecmp($name, $scopeBranch) === 0));
}
[$employeeScope, $employeeScopeParams] = \App\Support\BranchScope::employees($scopeBranch, 'e');
// The morning and afternoon QR codes are shared by every branch, so the super admin makes them. The overtime QR code belongs to a branch:
// each branch admin makes the one of their own branch (each branch approves its own overtime days), and the super admin never does.
$canGenerateQr = auth()->user()?->isSuperAdmin() ?? false;
$overtimeQrBranch = auth()->user()?->isBranchAdmin() ? \App\Support\BranchScope::current() : null;
$canGenerateOvertimeQr = $overtimeQrBranch !== null && $overtimeQrBranch !== '';

// Get all employees
$employeeListSql = "SELECT e.*, u.username, u.status as user_status FROM employees e LEFT JOIN users u ON e.user_id = u.id WHERE 1 = 1" . $employeeScope . " ORDER BY e.full_name";
$stmt = $pdo->prepare($employeeListSql);
$stmt->execute($employeeScopeParams);
$employees = $stmt->fetchAll();

// The one search box of the page: a branch, a barangay or an employee (a branch admin only has their own branch). It narrows the
// employee list of the Details tab and the Attendance tab alike; left empty it shows every employee the account may see.
$location = \App\Support\LocationFilter::fromRequest($request);
$searchTerm = $location['searchTerm'];
// The table shows the newest hire first; $employees stays in name order for the dropdowns.
$listEmployees = static function () use ($pdo, $employeeScope, $employeeScopeParams, $location): array {
    [$searchSql, $searchParams] = \App\Support\LocationFilter::employeeConditions($location, 'e');
    $stmt = $pdo->prepare("SELECT e.*, u.username, u.status as user_status FROM employees e LEFT JOIN users u ON e.user_id = u.id WHERE 1 = 1" . $employeeScope . $searchSql . " ORDER BY e.full_name");
    $stmt->execute([...$employeeScopeParams, ...$searchParams]);
    $listed = $stmt->fetchAll();
    usort($listed, static fn (array $a, array $b): int => [(string) ($b['created_at'] ?? ''), (int) $b['id']] <=> [(string) ($a['created_at'] ?? ''), (int) $a['id']]);

    return $listed;
};
$listedEmployees = $listEmployees();

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

// Update today's pending sessions without marking an employee absent before the session ends.
function updateAttendanceStatuses($pdo, $selectedDate) {
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');

    if ($selectedDate !== $today) {
        return;
    }

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('qr_morning_start', 'qr_morning_end', 'qr_afternoon_start', 'qr_afternoon_end', 'late_threshold_minutes')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $lateThreshold = max(0, (int) ($settings['late_threshold_minutes'] ?? 30));
    $sessions = [
        ['in' => 'morning_time_in', 'out' => 'morning_time_out', 'status' => 'status_morning', 'start' => $settings['qr_morning_start'] ?? '08:00:00', 'end' => $settings['qr_morning_end'] ?? '12:00:00'],
        ['in' => 'afternoon_time_in', 'out' => 'afternoon_time_out', 'status' => 'status_afternoon', 'start' => $settings['qr_afternoon_start'] ?? '13:00:00', 'end' => $settings['qr_afternoon_end'] ?? '17:00:00'],
    ];

    $stmt = $pdo->query("SELECT id, branch_name FROM employees WHERE status = 'active'");
    $employeeBranchesById = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($employeeBranchesById as $employeeId => $employeeBranchName) {
        $recordStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $recordStmt->execute([$employeeId, $today]);
        $attendance = $recordStmt->fetch();

        foreach ($sessions as $session) {
            $lateAt = date('H:i:s', strtotime($session['start']) + ($lateThreshold * 60));
            $newStatus = null;
            if (strcmp($currentTime, $session['end']) > 0) {
                $newStatus = 'absent';
            } elseif (strcmp($currentTime, $lateAt) > 0) {
                $newStatus = 'late';
            }

            if ($attendance && (!empty($attendance[$session['in']]) || !empty($attendance[$session['out']]))) {
                continue;
            }

            // A status entered by hand is final; the automatic clock must not overwrite it.
            if ($attendance && ($attendance['qr_code_used'] ?? null) === 'manual' && !empty($attendance[$session['status']])) {
                continue;
            }

            if (!$attendance && $newStatus === null) {
                continue;
            }

            if (!$attendance) {
                $insert = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, branch_name, {$session['status']}) VALUES (?, ?, ?, ?)");
                $insert->execute([$employeeId, $today, $employeeBranchName, $newStatus]);
                $recordStmt->execute([$employeeId, $today]);
                $attendance = $recordStmt->fetch();
                continue;
            }

            if ($attendance[$session['status']] !== $newStatus) {
                $update = $pdo->prepare("UPDATE attendance SET {$session['status']} = ? WHERE id = ?");
                $update->execute([$newStatus, $attendance['id']]);
                $attendance[$session['status']] = $newStatus;
            }
        }
    }
}

// Manual attendance: an admin records a day by hand (forgot to scan, no phone, etc.).
if ($request->isMethod('post') && $request->has('manual_attendance')) {
    try {
        $manualEmployeeId = (int) $request->input('manual_employee_id');
        $manualDate = (string) $request->input('manual_date');
        $manualRemarks = trim((string) $request->input('manual_remarks'));

        $employeeStmt = $pdo->prepare("SELECT id, full_name, position, branch_name FROM employees WHERE id = ? AND status = 'active'");
        $employeeStmt->execute([$manualEmployeeId]);
        $manualEmployee = $employeeStmt->fetch();
        if (!$manualEmployee || !\App\Support\BranchScope::canAccessBranch($manualEmployee['branch_name'])) {
            throw new Exception('Choose an active employee.');
        }
        if (!\App\Support\BranchScope::canManageEmployee($manualEmployee['position'])) {
            throw new Exception('Attendance for Admin employees is recorded by the super admin.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $manualDate) || !checkdate((int) substr($manualDate, 5, 2), (int) substr($manualDate, 8, 2), (int) substr($manualDate, 0, 4))) {
            throw new Exception('Enter a valid attendance date.');
        }
        if ($manualDate > date('Y-m-d')) {
            throw new Exception('Attendance cannot be recorded for a future date.');
        }

        $manualSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('qr_morning_start', 'qr_afternoon_start', 'late_threshold_minutes')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $manualLateThreshold = max(0, (int) ($manualSettings['late_threshold_minutes'] ?? 30));
        $manualSessions = [
            'morning' => ['start' => $manualSettings['qr_morning_start'] ?? '08:00:00', 'label' => 'Morning'],
            'afternoon' => ['start' => $manualSettings['qr_afternoon_start'] ?? '13:00:00', 'label' => 'Afternoon'],
        ];
        $normalizeTime = static function ($value): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
                throw new Exception('Enter times in a valid format.');
            }
            return strlen($value) === 5 ? $value . ':00' : $value;
        };

        $changes = [];
        $hours = [];
        foreach ($manualSessions as $key => $session) {
            $timeIn = $normalizeTime($request->input("manual_{$key}_in"));
            $timeOut = $normalizeTime($request->input("manual_{$key}_out"));
            $status = (string) $request->input("manual_{$key}_status");
            if (!in_array($status, ['', 'present', 'late', 'absent'], true)) {
                throw new Exception('Invalid attendance status.');
            }

            if ($status === 'absent') {
                // An absent session has no clock times.
                $changes["status_{$key}"] = 'absent';
                $changes["{$key}_time_in"] = null;
                $changes["{$key}_time_out"] = null;
                continue;
            }
            if ($timeIn === null && $timeOut === null && $status === '') {
                continue; // nothing entered for this session
            }
            if ($timeIn === null) {
                throw new Exception($session['label'] . ' time in is required for a present or late session.');
            }
            if ($timeOut !== null && strcmp($timeOut, $timeIn) <= 0) {
                throw new Exception($session['label'] . ' time out must be after time in.');
            }
            if ($status === '') {
                $lateAt = date('H:i:s', strtotime($session['start']) + ($manualLateThreshold * 60));
                $status = strcmp($timeIn, $lateAt) > 0 ? 'late' : 'present';
            }
            $changes["status_{$key}"] = $status;
            $changes["{$key}_time_in"] = $timeIn;
            $changes["{$key}_time_out"] = $timeOut;
        }

        // Overtime has no status: both times, or "no overtime" to take it away. The minutes in between go to the payroll.
        $overtimeIn = $normalizeTime($request->input('manual_overtime_in'));
        $overtimeOut = $normalizeTime($request->input('manual_overtime_out'));
        if ($request->boolean('manual_overtime_clear')) {
            $changes['overtime_time_in'] = null;
            $changes['overtime_time_out'] = null;
            $changes['overtime_minutes'] = 0;
        } elseif ($overtimeIn !== null || $overtimeOut !== null) {
            if ($overtimeIn === null || $overtimeOut === null) {
                throw new Exception('Enter both the overtime time in and the overtime time out.');
            }
            if (strcmp($overtimeOut, $overtimeIn) <= 0) {
                throw new Exception('Overtime time out must be after time in.');
            }
            $overtimeMinutes = \App\Support\AttendanceTime::minutesBetween($overtimeIn, $overtimeOut);
            if ($overtimeMinutes > \App\Support\AttendanceTime::MAX_OVERTIME_MINUTES) {
                throw new Exception('Overtime cannot be longer than 12 hours in a day.');
            }
            $changes['overtime_time_in'] = $overtimeIn;
            $changes['overtime_time_out'] = $overtimeOut;
            $changes['overtime_minutes'] = $overtimeMinutes;
        }
        if (!$changes) {
            throw new Exception('Fill in at least one session (morning, afternoon or overtime).');
        }

        $existingStmt = $pdo->prepare('SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?');
        $existingStmt->execute([$manualEmployee['id'], $manualDate]);
        $existing = $existingStmt->fetch() ?: [];

        // Total hours come from every complete in/out pair, including sessions left unchanged.
        $totalSeconds = 0;
        foreach (array_keys($manualSessions) as $key) {
            $in = array_key_exists("{$key}_time_in", $changes) ? $changes["{$key}_time_in"] : ($existing["{$key}_time_in"] ?? null);
            $out = array_key_exists("{$key}_time_out", $changes) ? $changes["{$key}_time_out"] : ($existing["{$key}_time_out"] ?? null);
            if ($in && $out) {
                $totalSeconds += max(0, strtotime($out) - strtotime($in));
            }
        }
        $changes['total_hours'] = round($totalSeconds / 3600, 2);
        $changes['qr_code_used'] = 'manual';
        $changes['branch_name'] = $manualEmployee['branch_name'];
        $who = auth()->user()->full_name ?? 'admin';
        $note = 'Manual entry by ' . $who . ($manualRemarks !== '' ? ': ' . $manualRemarks : '');
        $changes['notes'] = mb_substr($note, 0, 500);

        if ($existing) {
            $set = implode(', ', array_map(static fn ($column) => "$column = ?", array_keys($changes)));
            $pdo->prepare("UPDATE attendance SET $set WHERE id = ?")->execute([...array_values($changes), $existing['id']]);
        } else {
            $columns = array_merge(['employee_id', 'attendance_date'], array_keys($changes));
            $values = array_merge([$manualEmployee['id'], $manualDate], array_values($changes));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $pdo->prepare('INSERT INTO attendance (' . implode(', ', $columns) . ") VALUES ($placeholders)")->execute($values);
        }

        $success = 'Attendance saved for ' . $manualEmployee['full_name'] . ' on ' . date('M d, Y', strtotime($manualDate)) . '.';
        // Show the day that was just recorded.
        $selectedDate = $manualDate;
        $monthStart = $monthEnd = $manualDate;
        $activeTab = 'attendance';
    } catch (Exception $e) {
        $error = $e->getMessage();
        $activeTab = 'attendance';
        $manualAttendanceFailed = true;
    }
}

// Update attendance status only for the currently active date.
if (($attendanceView === 'date' && $selectedDate === date('Y-m-d')) || ($attendanceView === 'month' && $selectedMonth === date('Y-m'))) {
    updateAttendanceStatuses($pdo, $selectedDate);
}

// Only attendance recorded in the admin's branch is joined in, so a scan stays with the branch it was made in.
[$attendanceRowScope, $attendanceRowScopeParams] = \App\Support\BranchScope::attendance($scopeBranch, 'a');
// Include active employees without a scan so their status remains blank until it is due.
$attendanceQuery = "SELECT e.id as employee_id, e.full_name as employee_name, e.position, a.id as attendance_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.afternoon_time_in, a.afternoon_time_out, a.overtime_time_in, a.overtime_time_out, a.overtime_minutes, a.status_morning, a.status_afternoon, a.total_hours, a.qr_code_used, a.notes, COALESCE(a.branch_name, e.branch_name) AS branch_name FROM employees e LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date BETWEEN ? AND ?" . $attendanceRowScope . " WHERE e.status = 'active'";
$attendanceParams = [$monthStart, $monthEnd, ...$attendanceRowScopeParams];
if ($scopeBranch === '') {
    $attendanceQuery .= ' AND 1 = 0';
} elseif ($scopeBranch !== null) {
    // The branch's own employees, plus anyone with attendance recorded here (for example an employee who has since moved).
    $attendanceQuery .= " AND (LOWER(COALESCE(e.branch_name, '')) = LOWER(?) OR a.id IS NOT NULL)";
    $attendanceParams[] = $scopeBranch;
}
if ($selectedEmployeeId !== '') {
    $attendanceQuery .= " AND e.id = ?";
    $attendanceParams[] = $selectedEmployeeId;
}
// The same search for the attendance table. The branch recorded that day also counts (a scan stays with the branch it was made in,
// even when the employee has since moved), next to the employee's own branch.
[$locationSql, $locationParams] = \App\Support\LocationFilter::employeeConditions($location, 'e', ["NULLIF(a.branch_name, '')"]);
$attendanceQuery .= $locationSql;
array_push($attendanceParams, ...$locationParams);
$attendanceQuery .= " ORDER BY e.full_name";
$stmt = $pdo->prepare($attendanceQuery);
$stmt->execute($attendanceParams);
$attendanceRecords = \App\Support\AttendanceOrder::latestFirst($stmt->fetchAll());

// Get today's attendance count
[$attendanceScope, $attendanceScopeParams] = \App\Support\BranchScope::attendance($scopeBranch, 'a');
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance a WHERE a.attendance_date = CURRENT_DATE" . $attendanceScope);
$stmt->execute($attendanceScopeParams);
$todayAttendanceCount = $stmt->fetch()['total'];

// Get late threshold for late minutes calculation
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'late_threshold_minutes'");
$lateThresholdMinutes = (int) ($stmt->fetch()['setting_value'] ?? 30);

// Calculate late minutes for each attendance record
$sessionSchedule = \App\Support\AttendanceTime::schedule();
foreach ($attendanceRecords as &$record) {
    $record['late_minutes_morning'] = null;
    $record['late_minutes_afternoon'] = null;
    // Leaving a session before it ends (undertime): the minutes missing, shown next to the time out.
    $record['undertime_minutes_morning'] = \App\Support\AttendanceTime::undertimeOf($record['morning_time_in'], $record['morning_time_out'], $sessionSchedule['morning']['end']);
    $record['undertime_minutes_afternoon'] = \App\Support\AttendanceTime::undertimeOf($record['afternoon_time_in'], $record['afternoon_time_out'], $sessionSchedule['afternoon']['end']);

    if ($record['status_morning'] === 'late' && $record['morning_time_in']) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'qr_morning_start'");
        $stmt->execute();
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
        $stmt->execute();
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
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('qr_morning_start', 'qr_morning_end', 'qr_afternoon_start', 'qr_afternoon_end', 'qr_overtime_start', 'qr_overtime_end')");
$attendanceSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$qrMorningStart = $attendanceSettings['qr_morning_start'] ?? '08:00:00';
$qrMorningEnd = $attendanceSettings['qr_morning_end'] ?? '12:00:00';
$qrAfternoonStart = $attendanceSettings['qr_afternoon_start'] ?? '13:00:00';
$qrAfternoonEnd = $attendanceSettings['qr_afternoon_end'] ?? '17:00:00';
$qrOvertimeStart = $attendanceSettings['qr_overtime_start'] ?? '17:00:00';
$qrOvertimeEnd = $attendanceSettings['qr_overtime_end'] ?? '22:00:00';

// Today's QR codes: the shared morning and afternoon ones, and the overtime one of the branch admin's own branch. The super admin sees the
// overtime codes of every branch in a list ($overtimeQrByBranch), but has no overtime code of their own.
$qrToday = date('Y-m-d');
$loadQrCodes = static function () use ($pdo, $qrToday, $canGenerateOvertimeQr, $overtimeQrBranch): array {
    $sql = "SELECT * FROM qr_codes WHERE valid_date = ? AND status = 'active' AND (qr_type IN ('morning', 'afternoon')";
    $params = [$qrToday];
    if ($canGenerateOvertimeQr) {
        $sql .= " OR (qr_type = 'overtime' AND LOWER(COALESCE(branch_name, '')) = LOWER(?))";
        $params[] = $overtimeQrBranch;
    }
    $stmt = $pdo->prepare($sql . ') ORDER BY created_at DESC, id DESC LIMIT 9');
    $stmt->execute($params);

    return $stmt->fetchAll();
};
$qrCodes = $loadQrCodes();
$overtimeQrByBranch = [];
if (auth()->user()?->isSuperAdmin()) {
    $stmt = $pdo->prepare("SELECT branch_name, qr_code, valid_from, valid_until FROM qr_codes WHERE valid_date = ? AND qr_type = 'overtime' AND status = 'active' AND branch_name IS NOT NULL ORDER BY branch_name");
    $stmt->execute([$qrToday]);
    $overtimeQrByBranch = $stmt->fetchAll();
}

// Generate QR Code if requested
if ($request->isMethod('post') && $request->has('generate_qr')) {
    $qrType = (string) $request->input('qr_type');
    abort_unless($qrType === 'overtime' ? $canGenerateOvertimeQr : $canGenerateQr, 403);
    try {
        if (!in_array($qrType, ['morning', 'afternoon', 'overtime'], true)) {
            throw new Exception('Choose the morning, afternoon or overtime QR code.');
        }
        $validDate = date('Y-m-d');

        // The time window of the session, from the settings (overtime: 5 PM to 10 PM unless changed).
        $qrDefaults = ['morning' => ['08:00:00', '12:00:00'], 'afternoon' => ['13:00:00', '17:00:00'], 'overtime' => ['17:00:00', '22:00:00']];
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (?, ?)");
        $stmt->execute(["qr_{$qrType}_start", "qr_{$qrType}_end"]);
        $qrWindow = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $startTime = $qrWindow["qr_{$qrType}_start"] ?? $qrDefaults[$qrType][0];
        $endTime = $qrWindow["qr_{$qrType}_end"] ?? $qrDefaults[$qrType][1];
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        if (strlen($endTime) === 5) {
            $endTime .= ':00';
        }
        
        // The overtime code is the branch's own: it replaces only that branch's code of the day, and carries the branch. The morning and
        // afternoon codes are shared, so they have no branch.
        $qrBranch = $qrType === 'overtime' ? $overtimeQrBranch : null;

        // Deactivate old QR codes of same type
        if ($qrBranch !== null) {
            $stmt = $pdo->prepare("UPDATE qr_codes SET status = 'expired' WHERE qr_type = ? AND valid_date = ? AND LOWER(COALESCE(branch_name, '')) = LOWER(?)");
            $stmt->execute([$qrType, $validDate, $qrBranch]);
        } else {
            $stmt = $pdo->prepare("UPDATE qr_codes SET status = 'expired' WHERE qr_type = ? AND valid_date = ?");
            $stmt->execute([$qrType, $validDate]);
        }

        // Generate new QR code
        $qrCode = 'JFTECH-' . strtoupper($qrType) . '-' . date('Ymd') . '-' . bin2hex(random_bytes(4));

        $stmt = $pdo->prepare("INSERT INTO qr_codes (qr_code, qr_type, valid_date, valid_from, valid_until, status, created_by, branch_name) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
        $stmt->execute([$qrCode, $qrType, $validDate, $startTime, $endTime, auth()->id(), $qrBranch]);

        $success = ucfirst($qrType) . ' QR Code generated successfully!';

        // Refresh QR codes
        $qrCodes = $loadQrCodes();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Add/Edit Employee
if ($request->isMethod('post') && $request->has('save_employee')) {
    try {
        $employeeId = $request->input('employee_id') ?? null;
        $firstName = trim((string) $request->input('first_name'));
        $middleName = trim((string) $request->input('middle_name'));
        $lastName = trim((string) $request->input('last_name'));
        $fullName = employeeFullName($firstName, $middleName, $lastName);
        $position = trim((string) $request->input('position'));
        $contactNumber = trim($request->input('contact_number'));
        $email = trim($request->input('email'));
        $address = trim($request->input('address'));
        $dateHired = $request->input('date_hired');
        $dailyRate = $employeePositionRates[$position] ?? null;
        $branchName = trim((string) $request->input('branch_name'));
        // The branch is typed, so accept any capitalisation of a real branch name.
        foreach ($employeeBranches as $branchOption) {
            if (strcasecmp($branchOption, $branchName) === 0) {
                $branchName = $branchOption;
                break;
            }
        }
        if ($employeeId && !\App\Support\BranchScope::canAccessEmployee($employeeId)) {
            throw new Exception('That employee does not belong to your branch.');
        }
        if ($employeeId) {
            // The Admin employees (the branch admins) are managed by the super admin, so a branch admin cannot change their own rate.
            $targetPositionStmt = $pdo->prepare('SELECT position FROM employees WHERE id = ?');
            $targetPositionStmt->execute([$employeeId]);
            if (!\App\Support\BranchScope::canManageEmployee($targetPositionStmt->fetchColumn() ?: null)) {
                throw new Exception('Admin employees are managed by the super admin.');
            }
        }

        if ($firstName === '' || $lastName === '') {
            throw new Exception('First name and last name are required. Middle name is optional.');
        }
        if (! in_array($position, $employeePositions, true)) {
            throw new Exception('Select a valid employee position.');
        }
        if ($dailyRate === null) {
            throw new Exception('The selected position does not have a configured daily rate.');
        }
        if (!in_array($position, $selectablePositions, true)) {
            // Editing may keep the position the employee already has; only a new position is restricted.
            $currentPosition = null;
            if ($employeeId) {
                $currentPositionStmt = $pdo->prepare('SELECT position FROM employees WHERE id = ?');
                $currentPositionStmt->execute([$employeeId]);
                $currentPosition = $currentPositionStmt->fetchColumn();
            }
            if ($currentPosition !== $position) {
                throw new Exception($canAssignAdminPosition
                    ? 'The super admin only adds Admin employees. Branch admins add the technicians.'
                    : 'Only the super admin can give an employee the Admin position.');
            }
        }
        if (! in_array($branchName, $employeeBranches, true)) {
            throw new Exception('Select the branch this employee belongs to.');
        }
        if ($contactNumber !== '' && ! preg_match('/^\d{11}$/', $contactNumber)) {
            throw new Exception('Mobile number must contain exactly 11 digits.');
        }
        if (! $employeeId && blank($dateHired)) {
            throw new Exception('Date hired is required for a new employee.');
        }

        $pdo->beginTransaction();

        if ($employeeId) {
            $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, position = ?, branch_name = ?, department = NULL, contact_number = ?, email = ?, address = ?, date_hired = ?, daily_rate = ? WHERE id = ?");
            $stmt->execute([$fullName, $position, $branchName, $contactNumber, $email, $address, $dateHired, $dailyRate, $employeeId]);
            $pdo->commit();
            $success = 'Employee updated successfully! User accounts are managed in User Accounts.';
        } else {
            $employeeCode = 'EMP-' . date('Y') . '-' . strtoupper(uniqid());
            $stmt = $pdo->prepare("INSERT INTO employees (employee_code, user_id, full_name, position, branch_name, department, contact_number, email, address, date_hired, daily_rate, status) VALUES (?, NULL, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$employeeCode, $fullName, $position, $branchName, $contactNumber, $email, $address, $dateHired, $dailyRate]);
            
            $pdo->commit();
            $success = 'Employee added successfully. Create and link an account from User Accounts when needed.';
        }

        // Refresh employees list
        $stmt = $pdo->prepare($employeeListSql);
        $stmt->execute($employeeScopeParams);
        $employees = $stmt->fetchAll();
        $listedEmployees = $listEmployees();
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
        if (!\App\Support\BranchScope::canAccessEmployee($employeeId)) {
            throw new Exception('That employee does not belong to your branch.');
        }
        $absentPositionStmt = $pdo->prepare('SELECT position FROM employees WHERE id = ?');
        $absentPositionStmt->execute([$employeeId]);
        if (!\App\Support\BranchScope::canManageEmployee($absentPositionStmt->fetchColumn() ?: null)) {
            throw new Exception('Attendance for Admin employees is recorded by the super admin.');
        }
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
            // Create new record with absent status, in the employee's branch
            $absentBranchStmt = $pdo->prepare('SELECT branch_name FROM employees WHERE id = ?');
            $absentBranchStmt->execute([$employeeId]);
            $absentBranch = $absentBranchStmt->fetchColumn() ?: null;
            if ($session === 'morning') {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, branch_name, status_morning) VALUES (?, ?, ?, 'absent')");
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, branch_name, status_afternoon) VALUES (?, ?, ?, 'absent')");
            }
            $stmt->execute([$employeeId, $markDate, $absentBranch]);
        }

        $success = 'Employee marked as absent for ' . $session . ' session!';

        // Refresh attendance records
        $stmt = $pdo->prepare("SELECT a.*, e.full_name as employee_name, e.position FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.attendance_date = ?");
        $stmt->execute([$selectedDate]);
        $attendanceRecords = \App\Support\AttendanceOrder::latestFirst($stmt->fetchAll());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Payroll data
$stmt = $pdo->prepare("SELECT p.*, e.full_name as employee_name, e.position FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE 1 = 1" . $employeeScope . " ORDER BY p.created_at DESC LIMIT 20");
$stmt->execute($employeeScopeParams);
$payrollRecords = $stmt->fetchAll();
    return get_defined_vars();
};

<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Attendance Report
 */

$selectedEmployee = $request->query('employee_id') ?? '';
$selectedDate = $request->query('attendance_date') ?? date('Y-m-d');
$selectedStart = $request->query('start') ?? '';
$selectedEnd = $request->query('end') ?? '';

$query = "SELECT a.*, e.full_name, e.employee_code, e.position, e.department, COALESCE(a.branch_name, e.branch_name) AS branch_name
          FROM attendance a
          JOIN employees e ON a.employee_id = e.id
          WHERE TRUE";
// A branch admin only prints the attendance recorded in their own branch.
$scopeBranch = \App\Support\BranchScope::current();
[$attendanceScope, $params] = \App\Support\BranchScope::attendance($scopeBranch, 'a');
$query .= $attendanceScope;

if ($selectedEmployee) {
    $query .= " AND a.employee_id = ?";
    $params[] = $selectedEmployee;
}

// The same search box as the Attendance tab (a branch, a barangay or an employee); a branch admin's branch is always their own.
$location = \App\Support\LocationFilter::fromRequest($request);
[$locationSql, $locationParams] = \App\Support\LocationFilter::employeeConditions($location, 'e', ["NULLIF(a.branch_name, '')"]);
$query .= $locationSql;
array_push($params, ...$locationParams);

if ($selectedDate && !$selectedStart && !$selectedEnd) {
    $query .= " AND a.attendance_date = ?";
    $params[] = $selectedDate;
}

if ($selectedStart) {
    $query .= " AND a.attendance_date >= ?";
    $params[] = $selectedStart;
}

if ($selectedEnd) {
    $query .= " AND a.attendance_date <= ?";
    $params[] = $selectedEnd;
}

$query .= " ORDER BY a.attendance_date DESC, a.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceRecords = \App\Support\AttendanceOrder::latestFirst($stmt->fetchAll());

// Include today's active attendance QR codes in the printable report so staff
// can scan them directly from the same printed attendance sheet.
$qrSql = "SELECT qr_code, qr_type, valid_from, valid_until FROM qr_codes WHERE valid_date = ? AND status = 'active' AND qr_type IN ('morning', 'afternoon') ORDER BY qr_type";
$stmt = $pdo->prepare($qrSql);
$stmt->execute([date('Y-m-d')]);
$attendanceQrCodes = $stmt->fetchAll();

// Get employees for filter
[$filterScope, $filterScopeParams] = \App\Support\BranchScope::employees($scopeBranch, 'e');
$stmt = $pdo->prepare("SELECT e.id, e.employee_code, e.full_name, e.department FROM employees e WHERE 1 = 1" . $filterScope . " ORDER BY e.full_name");
$stmt->execute($filterScopeParams);
$allEmployees = $stmt->fetchAll();

$totalPresent = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'present' || $a['status_afternoon'] === 'present'; }));
$totalLate = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'late' || $a['status_afternoon'] === 'late'; }));
$totalAbsent = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'absent' && $a['status_afternoon'] === 'absent'; }));

// The same dates the query above used: the range when one is given, otherwise the single day, otherwise every date.
$periodLabel = ($selectedStart || $selectedEnd)
    ? \App\Support\ReportFormat::coverage($selectedStart, $selectedEnd)
    : \App\Support\ReportFormat::coverage($selectedDate, $selectedDate);
    return get_defined_vars();
};

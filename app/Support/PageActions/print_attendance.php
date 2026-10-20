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

$selectedBranch = trim((string) $request->query('branch', ''));
if ($selectedBranch !== '') {
    $query .= " AND COALESCE(a.branch_name, e.branch_name) = ?";
    $params[] = $selectedBranch;
}

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

$query .= " ORDER BY a.attendance_date DESC, a.morning_time_in DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll();

// Include today's active attendance QR codes in the printable report so staff
// can scan them directly from the same printed attendance sheet.
// The overtime code belongs to a branch: a branch admin's sheet has their own, the super admin's has none.
$qrSql = "SELECT qr_code, qr_type, valid_from, valid_until FROM qr_codes WHERE valid_date = ? AND status = 'active' AND (qr_type IN ('morning', 'afternoon')";
$qrParams = [date('Y-m-d')];
if ($scopeBranch !== null && $scopeBranch !== '') {
    $qrSql .= " OR (qr_type = 'overtime' AND LOWER(COALESCE(branch_name, '')) = LOWER(?))";
    $qrParams[] = $scopeBranch;
}
$stmt = $pdo->prepare($qrSql . ') ORDER BY qr_type');
$stmt->execute($qrParams);
$attendanceQrCodes = $stmt->fetchAll();

// Get employees for filter
[$filterScope, $filterScopeParams] = \App\Support\BranchScope::employees($scopeBranch, 'e');
$stmt = $pdo->prepare("SELECT e.id, e.employee_code, e.full_name, e.department FROM employees e WHERE 1 = 1" . $filterScope . " ORDER BY e.full_name");
$stmt->execute($filterScopeParams);
$allEmployees = $stmt->fetchAll();

$totalPresent = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'present' || $a['status_afternoon'] === 'present'; }));
$totalLate = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'late' || $a['status_afternoon'] === 'late'; }));
$totalAbsent = count(array_filter($attendanceRecords, function($a) { return $a['status_morning'] === 'absent' && $a['status_afternoon'] === 'absent'; }));

$periodLabel = '';
if ($selectedStart && $selectedEnd) {
    $periodLabel = date('M d, Y', strtotime($selectedStart)) . ' to ' . date('M d, Y', strtotime($selectedEnd));
} elseif ($selectedDate) {
    $periodLabel = date('M d, Y', strtotime($selectedDate));
} else {
    $periodLabel = 'All Dates';
}
    return get_defined_vars();
};

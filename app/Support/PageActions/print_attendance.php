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

$query = "SELECT a.*, e.full_name, e.employee_code, e.department 
          FROM attendance a 
          JOIN employees e ON a.employee_id = e.id 
          WHERE 1=1";
$params = [];

if ($selectedEmployee) {
    $query .= " AND a.employee_id = ?";
    $params[] = $selectedEmployee;
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

// Get employees for filter
$stmt = $pdo->query("SELECT id, employee_code, full_name, department FROM employees ORDER BY full_name");
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

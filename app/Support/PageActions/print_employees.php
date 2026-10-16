<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Employee Masterlist Report
 */

$selectedDepartment = $request->query('department') ?? '';
$searchTerm = $request->query('search') ?? '';

$query = "SELECT e.*, u.username, u.status as user_status 
          FROM employees e 
          LEFT JOIN users u ON e.user_id = u.id 
          WHERE TRUE";
// A branch admin only prints their own branch's employees.
[$employeeScope, $params] = \App\Support\BranchScope::employees(\App\Support\BranchScope::current(), 'e');
$query .= $employeeScope;

if ($selectedDepartment) {
    $query .= " AND e.department = ?";
    $params[] = $selectedDepartment;
}

if ($searchTerm) {
    $query .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ? OR e.position LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$query .= " ORDER BY e.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$totalEmployees = count($employees);
$activeEmployees = count(array_filter($employees, function($e) { return $e['status'] === 'active'; }));
$inactiveEmployees = count(array_filter($employees, function($e) { return $e['status'] === 'inactive'; }));
$terminatedEmployees = count(array_filter($employees, function($e) { return $e['status'] === 'terminated'; }));

$departments = array_unique(array_filter(array_column($employees, 'department')));
sort($departments);

$deptLabel = '';
if ($selectedDepartment) {
    $deptLabel = $selectedDepartment;
}
    return get_defined_vars();
};

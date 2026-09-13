<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();



$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$payrollCssVersion = filemtime(public_path('css/payroll.css'));

$success = '';
$error = '';

$selectedEmployee = $request->query('employee_id') ?? '';
$selectedStatus = $request->query('status') ?? '';
$selectedStart = $request->query('period_start') ?? '';
$selectedEnd = $request->query('period_end') ?? '';

$stmt = $pdo->query("SELECT id, employee_code, full_name, position, department, daily_rate, status FROM employees ORDER BY full_name");
$employees = $stmt->fetchAll();

$employeeMap = [];
foreach ($employees as $employee) {
    $employeeMap[$employee['id']] = $employee;
}
$hasEmployees = count($employees) > 0;

function getAttendanceDays($pdo, $employeeId, $periodStart, $periodEnd) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(
                CASE
                    WHEN COALESCE(total_hours, 0) > 0
                         OR morning_time_in IS NOT NULL
                         OR afternoon_time_in IS NOT NULL
                    THEN 1
                END
            ) AS days_worked,
            COALESCE(SUM(total_hours), 0) AS total_hours
        FROM attendance
        WHERE employee_id = ?
          AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->execute([$employeeId, $periodStart, $periodEnd]);
    return $stmt->fetch();
}

if ($request->has('action') && $request->query('action') === 'attendance') {
    header('Content-Type: application/json');
    $empId = (int) ($request->query('employee_id') ?? 0);
    $start = $request->query('start') ?? '';
    $end = $request->query('end') ?? '';
    if ($empId && $start && $end) {
        $att = getAttendanceDays($pdo, $empId, $start, $end);
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status_morning = 'late' THEN 1 ELSE 0 END) +
                SUM(CASE WHEN status_afternoon = 'late' THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN status_morning = 'absent' THEN 1 ELSE 0 END) +
                SUM(CASE WHEN status_afternoon = 'absent' THEN 1 ELSE 0 END) AS absent_count
            FROM attendance
            WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
        ");
        $stmt->execute([$empId, $start, $end]);
        $ded = $stmt->fetch();
        $empStmt = $pdo->prepare("SELECT daily_rate FROM employees WHERE id = ?");
        $empStmt->execute([$empId]);
        $emp = $empStmt->fetch();
        $dailyRate = (float) ($emp['daily_rate'] ?? 0);
        $hourlyRate = $dailyRate / 8;
        $lateCount = (int) ($ded['late_count'] ?? 0);
        $absentCount = (int) ($ded['absent_count'] ?? 0);
        echo json_encode([
            'days_worked' => (int) ($att['days_worked'] ?? 0),
            'total_hours' => (float) ($att['total_hours'] ?? 0),
            'late_count' => $lateCount,
            'absent_count' => $absentCount,
            'late_amount' => $lateCount * $hourlyRate,
            'absent_amount' => $absentCount * ($dailyRate / 2),
        ]);
    } else {
        echo json_encode(['days_worked' => 0, 'total_hours' => 0, 'late_count' => 0, 'absent_count' => 0, 'late_amount' => 0, 'absent_amount' => 0]);
    }
    exit;
}

if ($request->isMethod('post')) {
    try {
        $payrollId = $request->input('payroll_id') ?? null;
        $employeeId = (int) ($request->input('employee_id') ?? 0);
        $periodStart = $request->input('payroll_period_start') ?? '';
        $periodEnd = $request->input('payroll_period_end') ?? '';
        $dailyRate = (float) ($request->input('daily_rate') ?? 0);
        $status = $request->input('status') ?? 'draft';

        if (!$employeeId || !isset($employeeMap[$employeeId])) {
            throw new Exception('Please select a valid employee.');
        }

        if (!$periodStart || !$periodEnd) {
            throw new Exception('Payroll period start and end dates are required.');
        }

        if ($periodEnd < $periodStart) {
            throw new Exception('Payroll period end date cannot be earlier than the start date.');
        }

        $allowedStatuses = ['draft', 'processed', 'paid'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'draft';
        }

        if ($dailyRate <= 0) {
            $dailyRate = (float) $employeeMap[$employeeId]['daily_rate'];
        }
        if ($dailyRate <= 0) {
            throw new Exception('Please enter a valid daily rate for the selected employee.');
        }

        $earningsKeys = [
            'basic_salary', 'overtime_pay', 'regular_holiday', 'special_holiday', 'night_differential',
            'incentives', 'allowances', 'leave_pay', 'rest_day_pay', 'salary_adjustment', 'other_earnings'
        ];
        $deductionKeys = [
            'sss', 'philhealth', 'pagibig', 'withholding_tax', 'cash_advance', 'salary_loan', 'hdmf_loan', 'other_deductions'
        ];
        $inputKeys = array_merge(['overtime_hours', 'late', 'undertime', 'absent'], $earningsKeys, $deductionKeys);

        $input = [];
        foreach ($inputKeys as $key) {
            $input[$key] = max(0, (float) ($request->input($key) ?? 0));
        }

        if ($request->has('total_days_worked') && $request->input('total_days_worked') !== '') {
            $daysWorked = (int) $request->input('total_days_worked');
        } else {
            $att = getAttendanceDays($pdo, $employeeId, $periodStart, $periodEnd);
            $daysWorked = (int) ($att['days_worked'] ?? 0);
        }
        $totalHours = (float) ($request->input('total_hours') ?? 0);

        $monthlyBasic = $dailyRate * 26;
        $hourlyRate = $dailyRate / 8;

        if ($input['basic_salary'] <= 0 && $daysWorked > 0) {
            $input['basic_salary'] = $daysWorked * $dailyRate;
        }

        if ($input['late'] <= 0 || $input['absent'] <= 0) {
            $stmt = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN status_morning = 'late' THEN 1 ELSE 0 END) +
                    SUM(CASE WHEN status_afternoon = 'late' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status_morning = 'absent' THEN 1 ELSE 0 END) +
                    SUM(CASE WHEN status_afternoon = 'absent' THEN 1 ELSE 0 END) AS absent_count
                FROM attendance
                WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
            ");
            $stmt->execute([$employeeId, $periodStart, $periodEnd]);
            $ded = $stmt->fetch();
            $lateCount = (int) ($ded['late_count'] ?? 0);
            $absentCount = (int) ($ded['absent_count'] ?? 0);
            if ($input['late'] <= 0 && $lateCount > 0) {
                $input['late'] = $lateCount * $hourlyRate;
            }
            if ($input['absent'] <= 0 && $absentCount > 0) {
                $input['absent'] = $absentCount * ($dailyRate / 2);
            }
        }

        $grossPay = 0;
        foreach ($earningsKeys as $key) {
            $grossPay += $input[$key];
        }
        $totalDeductions = 0;
        foreach ($deductionKeys as $key) {
            $totalDeductions += $input[$key];
        }
        $netPay = max(0, $grossPay - $totalDeductions);

        $columns = [
            'employee_id' => $employeeId,
            'payroll_period_start' => $periodStart,
            'payroll_period_end' => $periodEnd,
            'total_days_worked' => $daysWorked,
            'total_hours' => $totalHours,
            'daily_rate' => $dailyRate,
            'monthly_basic_salary' => $monthlyBasic,
            'hourly_rate' => $hourlyRate,
            'overtime_hours' => $input['overtime_hours'],
            'late' => $input['late'],
            'undertime' => $input['undertime'],
            'absent' => $input['absent'],
            'basic_salary' => $input['basic_salary'],
            'overtime_pay' => $input['overtime_pay'],
            'regular_holiday' => $input['regular_holiday'],
            'special_holiday' => $input['special_holiday'],
            'night_differential' => $input['night_differential'],
            'incentives' => $input['incentives'],
            'allowances' => $input['allowances'],
            'leave_pay' => $input['leave_pay'],
            'rest_day_pay' => $input['rest_day_pay'],
            'salary_adjustment' => $input['salary_adjustment'],
            'other_earnings' => $input['other_earnings'],
            'gross_pay' => $grossPay,
            'sss' => $input['sss'],
            'philhealth' => $input['philhealth'],
            'pagibig' => $input['pagibig'],
            'withholding_tax' => $input['withholding_tax'],
            'cash_advance' => $input['cash_advance'],
            'salary_loan' => $input['salary_loan'],
            'hdmf_loan' => $input['hdmf_loan'],
            'other_deductions' => $input['other_deductions'],
            'deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'status' => $status,
        ];

        $colNames = array_keys($columns);
        $colValues = array_values($columns);

        if ($payrollId) {
            $setParts = array_map(function ($c) { return "$c = ?"; }, $colNames);
            $stmt = $pdo->prepare("UPDATE payroll SET " . implode(', ', $setParts) . " WHERE id = ?");
            $stmt->execute(array_merge($colValues, [$payrollId]));
            $success = 'Payroll record updated successfully.';
        } else {
            $placeholders = array_fill(0, count($colNames), '?');
            $stmt = $pdo->prepare("INSERT INTO payroll (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmt->execute($colValues);
            $success = 'Payroll record created successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$view = $request->query('view') ?? '';
$showArchived = $view === 'archived';

if ($request->has('delete') && $request->query('delete')) {
    try {
        $stmt = $pdo->prepare("UPDATE payroll SET archived = 1 WHERE id = ?");
        $stmt->execute([$request->query('delete')]);
        $success = 'Payroll record archived successfully.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($request->has('restore') && $request->query('restore')) {
    try {
        $stmt = $pdo->prepare("UPDATE payroll SET archived = 0 WHERE id = ?");
        $stmt->execute([$request->query('restore')]);
        $success = 'Payroll record restored successfully.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($request->has('purge') && $request->query('purge')) {
    try {
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ? AND archived = 1");
        $stmt->execute([$request->query('purge')]);
        $success = 'Payroll record permanently deleted.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$where = [];
$params = [];

if ($selectedEmployee !== '') {
    $where[] = 'p.employee_id = ?';
    $params[] = $selectedEmployee;
}

if ($selectedStatus !== '') {
    $where[] = 'p.status = ?';
    $params[] = $selectedStatus;
}

if ($selectedStart !== '') {
    $where[] = 'p.payroll_period_start >= ?';
    $params[] = $selectedStart;
}

if ($selectedEnd !== '') {
    $where[] = 'p.payroll_period_end <= ?';
    $params[] = $selectedEnd;
}

if ($showArchived) {
    $where[] = 'p.archived = 1';
} else {
    $where[] = '(p.archived = 0 OR p.archived IS NULL)';
}

$payrollSql = "
    SELECT p.*, e.full_name AS employee_name, e.employee_code, e.position, e.department, e.status AS employment_status
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
";

if ($where) {
    $payrollSql .= ' WHERE ' . implode(' AND ', $where);
}

$payrollSql .= ' ORDER BY p.created_at DESC, p.payroll_period_end DESC';

$stmt = $pdo->prepare($payrollSql);
$stmt->execute($params);
$payrollRecords = $stmt->fetchAll();

$statStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(net_pay), 0) AS net,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) AS processed,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid
    FROM payroll
    WHERE archived = 0 OR archived IS NULL
");
$stats = $statStmt->fetch();
$archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM payroll WHERE archived = 1")->fetchColumn();

$totalRecords = (int) $stats['total'];
$totalNetPay = (float) $stats['net'];
$processedCount = (int) $stats['processed'];
$paidCount = (int) $stats['paid'];

$employeeJson = json_encode(
    array_values($employees),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
    return get_defined_vars();
};

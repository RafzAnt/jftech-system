<?php

return function (\Illuminate\Http\Request $request): array {
    $payrollId = (int) $request->query('id');
    abort_unless($payrollId > 0, 404);

    $record = \Illuminate\Support\Facades\DB::table('payroll as p')
        ->join('employees as e', 'p.employee_id', '=', 'e.id')
        ->select('p.*', 'e.full_name as employee_name', 'e.employee_code', 'e.position', 'e.department', 'e.branch_name as employee_branch', 'e.status as employment_status')
        ->where('p.id', $payrollId)
        ->first();

    abort_unless($record, 404);
    \App\Support\BranchScope::abortUnlessEmployee($record->employee_id);

    return compact('record');
};

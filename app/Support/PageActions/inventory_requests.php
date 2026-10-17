<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

return function (Request $request): array {
    $filterStatus = $request->query('status', 'all');
    abort_unless(in_array($filterStatus, ['all', 'pending', 'approved', 'denied'], true), 404);

    // A request belongs to the branch of the technician who made it.
    $branch = \App\Support\BranchScope::resolve($request->query('branch'));
    [$employeeSql, $employeeParams] = \App\Support\BranchScope::viaEmployee($branch, 'ir.employee_id');
    $inBranch = static fn ($query) => $employeeSql === '' ? $query : $query->whereRaw(substr($employeeSql, strlen(' AND ')), $employeeParams);

    $requests = $inBranch(DB::table('inventory_requests as ir')
        ->leftJoin('users as u', 'ir.approved_by', '=', 'u.id'))
        ->leftJoin('employees as emp', 'emp.id', '=', 'ir.employee_id')
        ->select('ir.*', 'u.username as approved_by_name', 'u.full_name as approved_by_full_name', 'emp.branch_name as employee_branch')
        ->when($filterStatus !== 'all', fn ($query) => $query->where('ir.status', $filterStatus))
        ->orderByDesc('ir.created_at')
        ->get()
        ->map(fn ($row) => (array) $row)
        ->all();

    return [
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'inventoryRequestsCssVersion' => @filemtime(public_path('css/inventory_requests.css')) ?: 1,
        'filterStatus' => $filterStatus,
        'requests' => $requests,
        'pendingCount' => $inBranch(DB::table('inventory_requests as ir'))->where('ir.status', 'pending')->count(),
        'branch' => $branch,
        'inventoryBranches' => array_keys(config('bohol_branches', [])),
        'canDecideRequests' => (bool) auth()->user()?->isBranchAdmin(),
        'success' => session('success', ''),
        'error' => session('error', ''),
    ];
};

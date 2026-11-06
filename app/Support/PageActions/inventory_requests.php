<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return function (Request $request): array {
    $filterStatus = $request->query('status', 'all');
    abort_unless(in_array($filterStatus, ['all', 'pending', 'approved', 'denied'], true), 404);

    // A request belongs to the branch of the technician who made it.
    $branch = \App\Support\BranchScope::resolve($request->query('branch'));
    [$employeeSql, $employeeParams] = \App\Support\BranchScope::viaEmployee($branch, 'ir.employee_id');
    $inBranch = static fn ($query) => $employeeSql === '' ? $query : $query->whereRaw(substr($employeeSql, strlen(' AND ')), $employeeParams);

    $rows = $inBranch(DB::table('inventory_requests as ir')
        ->leftJoin('users as u', 'ir.approved_by', '=', 'u.id'))
        ->leftJoin('employees as emp', 'emp.id', '=', 'ir.employee_id')
        // An approved installation request stays "pending" on the job side until the technician's installation report is confirmed.
        ->leftJoin('client_installations as ci', 'ci.id', '=', 'ir.installation_id')
        ->select('ir.*', 'u.username as approved_by_name', 'u.full_name as approved_by_full_name', 'emp.branch_name as employee_branch', 'ci.status as installation_status')
        ->when($filterStatus !== 'all', fn ($query) => $query->where('ir.status', $filterStatus))
        ->orderByDesc('ir.created_at')->orderByDesc('ir.id')
        ->get()->map(fn ($row) => (array) $row)->all();

    // Show a multi-item app submission as one admin request, while keeping legacy rows as individual requests.
    $hasGroups = Schema::hasColumn('inventory_requests', 'request_group_id');
    $requests = [];
    foreach ($rows as $row) {
        $groupId = $hasGroups ? ($row['request_group_id'] ?? null) : null;
        $key = $groupId ? 'group:' . $groupId : 'row:' . $row['id'];
        if (! isset($requests[$key])) {
            $requests[$key] = $row + ['items' => [], 'request_ids' => [], 'total_quantity' => 0];
        }
        $requests[$key]['items'][] = [
            'id' => (int) $row['id'], 'item_name' => $row['item_name'], 'quantity' => (int) $row['quantity'],
            'comments' => $row['comments'] ?? '', 'item_condition' => $row['item_condition'] ?? '',
        ];
        $requests[$key]['request_ids'][] = (int) $row['id'];
        $requests[$key]['total_quantity'] += (int) $row['quantity'];
    }
    $requests = array_values($requests);
    $pendingRows = $inBranch(DB::table('inventory_requests as ir'))->where('ir.status', 'pending')
        ->get($hasGroups ? ['ir.id', 'ir.request_group_id'] : ['ir.id']);
    $pendingCount = $pendingRows->map(fn ($row) => $hasGroups && $row->request_group_id
        ? 'group:' . $row->request_group_id
        : 'row:' . $row->id)->unique()->count();

    return [
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'inventoryRequestsCssVersion' => @filemtime(public_path('css/inventory_requests.css')) ?: 1,
        'filterStatus' => $filterStatus,
        'requests' => $requests,
        'pendingCount' => $pendingCount,
        'branch' => $branch,
        'inventoryBranches' => array_keys(config('bohol_branches', [])),
        'canDecideRequests' => (bool) auth()->user()?->isBranchAdmin(),
        'success' => session('success', ''),
        'error' => session('error', ''),
    ];
};

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

return function (Request $request): array {
    $filterStatus = $request->query('status', 'all');
    abort_unless(in_array($filterStatus, ['all', 'pending', 'approved', 'denied'], true), 404);

    $requests = DB::table('inventory_requests as ir')
        ->leftJoin('users as u', 'ir.approved_by', '=', 'u.id')
        ->select('ir.*', 'u.username as approved_by_name', 'u.full_name as approved_by_full_name')
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
        'pendingCount' => DB::table('inventory_requests')->where('status', 'pending')->count(),
        'success' => session('success', ''),
        'error' => session('error', ''),
    ];
};

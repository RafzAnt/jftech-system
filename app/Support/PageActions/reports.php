<?php

use App\Support\BranchScope;
use App\Support\PeriodReport;

return function (\Illuminate\Http\Request $request): array {
    [$periodStart, $periodEnd, $month] = PeriodReport::periodFromRequest($request);
    // A branch admin always gets their own branch; the super admin can pick one or see all.
    $branch = BranchScope::resolve($request->query('branch'));

    return [
        'currentPage' => 'reports',
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'reportsCssVersion' => @filemtime(public_path('css/reports.css')) ?: 1,
        'month' => $month,
        'periodStart' => $periodStart,
        'periodEnd' => $periodEnd,
        'branch' => $branch,
        'branchLabel' => $branch ?? 'All branches',
        'reportBranches' => array_keys(config('bohol_branches', [])),
        'report' => PeriodReport::build($periodStart, $periodEnd, $branch),
    ];
};

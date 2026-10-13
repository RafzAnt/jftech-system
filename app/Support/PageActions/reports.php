<?php

use App\Support\PeriodReport;

return function (\Illuminate\Http\Request $request): array {
    [$periodStart, $periodEnd, $month] = PeriodReport::periodFromRequest($request);

    return [
        'currentPage' => 'reports',
        'themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1,
        'reportsCssVersion' => @filemtime(public_path('css/reports.css')) ?: 1,
        'month' => $month,
        'periodStart' => $periodStart,
        'periodEnd' => $periodEnd,
        'report' => PeriodReport::build($periodStart, $periodEnd),
    ];
};

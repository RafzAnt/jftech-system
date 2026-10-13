<?php

use App\Support\PeriodReport;

return function (\Illuminate\Http\Request $request): array {
    [$from, $to] = PeriodReport::periodFromRequest($request);

    return [
        'from' => $from,
        'to' => $to,
        'report' => PeriodReport::build($from, $to),
    ];
};

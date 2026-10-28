<?php

use App\Support\BranchScope;
use App\Support\PeriodReport;

return function (\Illuminate\Http\Request $request): array {
    [$from, $to] = PeriodReport::periodFromRequest($request);
    $branch = BranchScope::resolve($request->query('branch'));

    return [
        'from' => $from,
        'to' => $to,
        'selectedBranch' => $branch,
        'report' => PeriodReport::build($from, $to, $branch),
    ];
};

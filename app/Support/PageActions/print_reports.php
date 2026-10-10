<?php

use App\Support\ReportService;

return function (\Illuminate\Http\Request $request): array {
    /**
     * JFTECH WiFi Billing & Management System
     * Printable Activity Report (PDF via browser Print-to-PDF)
     *
     * Reuses ReportService so the printed/exported document always reflects the
     * exact same real data shown on screen.
     */

    $service = new ReportService();

    $reportName      = $request->query('report_name', 'JFTECH Activity Report');
    $reportViewType  = $request->query('report_view_type', 'custom');
    $filterDate      = $request->query('date') ?? '';
    $filterFrom      = $request->query('from') ?? '';
    $filterTo        = $request->query('to') ?? '';
    $filterYear      = (int) ($request->query('year') ?? (int) date('Y'));
    $filterType      = $request->query('report_type') ?? 'all';
    $filterStatus    = $request->query('status') ?? 'all';
    $filterPerson    = $request->query('person') ?? '';
    $filterCategory  = $request->query('category') ?? 'all';

    $filters = [
        'date'        => $filterDate,
        'from'        => $filterFrom,
        'to'          => $filterTo,
        'report_type' => $filterType,
        'status'      => $filterStatus,
        'person'      => $filterPerson,
        'category'    => $filterCategory,
    ];

    // For yearly reports the from/to may be carried as year; normalise.
    if ($reportViewType === 'yearly' && (empty($filterFrom) || empty($filterTo))) {
        $filters['from'] = sprintf('%04d-01-01', $filterYear);
        $filters['to']   = sprintf('%04d-12-31', $filterYear);
    }

    $records  = $service->activityFeed($filters, 5000);
    $summary  = $service->activitySummary($filters);
    $reportTypes = $service->reportTypes();

    // Build a human readable period label.
    if ($filters['date']) {
        $rangeLabel = date('M d, Y', strtotime($filters['date']));
    } elseif ($filters['from'] || $filters['to']) {
        $rangeLabel = ($filters['from'] ? date('M d, Y', strtotime($filters['from'])) : 'Start')
            . ' to ' . ($filters['to'] ? date('M d, Y', strtotime($filters['to'])) : date('M d, Y'));
    } else {
        $rangeLabel = 'All Dates';
    }

    // --- Enrich with breakdown / comparison / performance data ---
    $dailyBreakdown    = null;
    $monthlyBreakdown  = null;
    $previousSummary   = null;
    $comparisonPeriod  = '';
    $percentageChanges = [];
    $performance       = null;
    $selectedYear      = $filterYear;
    $selectedMonth     = (int) ($request->query('month') ?? (int) date('m'));

    if (in_array($reportViewType, ['monthly', 'yearly', 'custom'])) {
        $dailyBreakdown = $service->dailyBreakdown($filters);
    }

    if ($reportViewType === 'yearly') {
        $selectedYear = $filterYear;
        $monthlyBreakdown = $service->monthlyBreakdown($selectedYear, $filters);
        $performance = $service->performanceSummary($reportViewType, $filters, $selectedYear, $selectedMonth, $summary);

        // Previous year comparison
        $prevYear = $selectedYear - 1;
        $prevFilters = $filters;
        $prevFilters['from'] = sprintf('%04d-01-01', $prevYear);
        $prevFilters['to']   = sprintf('%04d-12-31', $prevYear);
        $previousSummary = $service->activitySummary($prevFilters);
        $comparisonPeriod = (string) $prevYear;
        $percentageChanges = [
            'total'     => $service->percentageChange($summary['total'], $previousSummary['total']),
            'completed' => $service->percentageChange($summary['completed'], $previousSummary['completed']),
            'pending'   => $service->percentageChange($summary['pending'], $previousSummary['pending']),
            'cancelled' => $service->percentageChange($summary['cancelled'], $previousSummary['cancelled']),
            'overdue'   => $service->percentageChange($summary['overdue'], $previousSummary['overdue']),
            'amount'    => $service->percentageChange($summary['amount'], $previousSummary['amount']),
        ];
    } elseif ($reportViewType === 'monthly') {
        $performance = $service->performanceSummary($reportViewType, $filters, $selectedYear, $selectedMonth, $summary);

        // Previous month comparison
        $prevMonth = $selectedMonth - 1;
        $prevYear = $selectedYear;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $prevFilters = $filters;
        $prevStart = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
        $prevFilters['from'] = $prevStart;
        $prevFilters['to']   = date('Y-m-t', strtotime($prevStart));
        $previousSummary = $service->activitySummary($prevFilters);
        $comparisonPeriod = date('F Y', strtotime($prevStart));
        $percentageChanges = [
            'total'     => $service->percentageChange($summary['total'], $previousSummary['total']),
            'completed' => $service->percentageChange($summary['completed'], $previousSummary['completed']),
            'pending'   => $service->percentageChange($summary['pending'], $previousSummary['pending']),
            'cancelled' => $service->percentageChange($summary['cancelled'], $previousSummary['cancelled']),
            'overdue'   => $service->percentageChange($summary['overdue'], $previousSummary['overdue']),
            'amount'    => $service->percentageChange($summary['amount'], $previousSummary['amount']),
        ];
    } elseif ($reportViewType === 'daily') {
        $performance = $service->performanceSummary($reportViewType, $filters, $selectedYear, $selectedMonth, $summary);
    } elseif ($reportViewType === 'custom') {
        $performance = $service->performanceSummary($reportViewType, $filters, $selectedYear, $selectedMonth, $summary);
    }

    return get_defined_vars();
};

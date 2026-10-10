<?php

namespace App\Http\Controllers;

use App\Support\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected $service;

    public function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    /**
     * Export the (filtered) activity feed as a CSV (Excel-openable).
     * Reuses ReportService so the file matches the on-screen report exactly.
     */
    public function exportCsv(Request $request)
    {
        $filters = $this->extractFilters($request);
        $reportName = $this->safeFilename($request->query('report_name', 'JFTECH Activity Report'));
        $rangeLabel = $this->buildRangeLabel($filters);

        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . trim($reportName) . ' - ' . $rangeLabel . '.csv"');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $response->setCallback(function () use ($filters) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date & Time', 'Record No.', 'Record Type', 'Activity', 'Name', 'Reference Code', 'Status', 'Amount', 'Quantity', 'Notes'], ',', '"', '');
            $records = $this->service->activityFeed($filters, 5000);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r['activity_datetime'] ?? '',
                    $r['record_id'] ?? '',
                    $this->service->reportTypes()[$r['report_type'] ?? ''] ?? ($r['report_type'] ?? ''),
                    $r['activity_type'] ?? '',
                    $r['person'] ?? '',
                    $r['record_code'] ?? '',
                    $r['status'] ?? '',
                    $r['amount'] !== null ? (float) $r['amount'] : '',
                    $r['quantity'] !== null ? (int) $r['quantity'] : '',
                    $r['remarks'] ?? '',
                ], ',', '"', '');
            }
            fclose($handle);
        });

        return $response;
    }

    /**
     * Export an Excel-friendly format. Without PhpSpreadsheet we serve an
     * Excel-compatible CSV (UTF-8 BOM) which opens cleanly in Excel.
     */
    public function exportExcel(Request $request)
    {
        $filters = $this->extractFilters($request);
        $reportName = $this->safeFilename($request->query('report_name', 'JFTECH Activity Report'));
        $rangeLabel = $this->buildRangeLabel($filters);

        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . trim($reportName) . ' - ' . $rangeLabel . '.xls"');

        $response->setCallback(function () use ($filters) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date & Time', 'Record No.', 'Record Type', 'Activity', 'Name', 'Reference Code', 'Status', 'Amount', 'Quantity', 'Notes'], ',', '"', '');
            $records = $this->service->activityFeed($filters, 5000);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r['activity_datetime'] ?? '',
                    $r['record_id'] ?? '',
                    $this->service->reportTypes()[$r['report_type'] ?? ''] ?? ($r['report_type'] ?? ''),
                    $r['activity_type'] ?? '',
                    $r['person'] ?? '',
                    $r['record_code'] ?? '',
                    $r['status'] ?? '',
                    $r['amount'] !== null ? (float) $r['amount'] : '',
                    $r['quantity'] !== null ? (int) $r['quantity'] : '',
                    $r['remarks'] ?? '',
                ], ',', '"', '');
            }
            fclose($handle);
        });

        return $response;
    }

    /** Show the report_history listing (admin only — route is middleware-guarded). */
    public function history(Request $request)
    {
        $history = $this->service->reportHistory();
        $generatedBy = $request->user() ? $request->user()->full_name : 'System Administrator';
        return view('pages.report_history', compact('history', 'generatedBy'));
    }

    /** Delete a single generated report from history (admin only). */
    public function destroyHistory(Request $request, $id)
    {
        $this->authorizeReportAction();
        $this->service->deleteReport((int) $id);
        return back()->with('msg', 'Report entry removed from history.');
    }

    protected function extractFilters(Request $request): array
    {
        return [
            'date'        => $request->query('date') ?? '',
            'from'        => $request->query('from') ?? '',
            'to'          => $request->query('to') ?? '',
            'report_type' => $request->query('report_type') ?? 'all',
            'status'      => $request->query('status') ?? 'all',
            'person'      => $request->query('person') ?? '',
            'category'    => $request->query('category') ?? 'all',
        ];
    }

    protected function buildRangeLabel(array $filters): string
    {
        if ($filters['date']) return date('M d, Y', strtotime($filters['date']));
        if ($filters['from'] || $filters['to']) {
            return ($filters['from'] ? date('M d, Y', strtotime($filters['from'])) : 'Start')
                . ' to ' . ($filters['to'] ? date('M d, Y', strtotime($filters['to'])) : date('M d, Y'));
        }
        return 'All Dates';
    }

    protected function authorizeReportAction(): void
    {
        $user = request()->user();
        abort_unless($user && in_array((string) $user->role, ['admin'], true) && $user->status === 'active', 403);
    }

    /** Keep user-provided report names safe for use in HTTP download headers. */
    protected function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9 _.-]/', '', $name) ?: 'JFTECH Activity Report';
        return trim($name) ?: 'JFTECH Activity Report';
    }
}

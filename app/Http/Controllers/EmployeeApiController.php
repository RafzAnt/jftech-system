<?php

namespace App\Http\Controllers;

use App\Support\Installations;
use App\Support\NapBoxes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What the employee app needs besides sign in, attendance and stock (those are in ApiController): the home
 * screen, NAP boxes and the transaction history.
 * Every route here is behind the employee token, so the employee is always the one who signed in.
 */
class EmployeeApiController extends Controller
{
    /** The connection test of the app's settings: it needs no sign in. */
    public function ping(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'JFTECH API is reachable.', 'time' => now()->toIso8601String()]);
    }

    public function me(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $today = DB::table('attendance')->where('employee_id', $employee->id)->whereDate('attendance_date', today())->first();

        $firstIn = $today?->morning_time_in ?: $today?->afternoon_time_in;
        $latestIn = $today?->afternoon_time_in ?: $today?->morning_time_in;
        $latestOut = $today?->afternoon_time_in ? $today?->afternoon_time_out : $today?->morning_time_out;

        return response()->json([
            'success' => true,
            'employee' => [
                'id' => (int) $employee->id, 'employee_code' => $employee->employee_code, 'full_name' => $employee->full_name,
                'position' => $employee->position, 'branch_name' => $employee->branch_name,
            ],
            'today' => [
                'date' => today()->toDateString(),
                'check_in_time' => $firstIn,
                'morning_time_in' => $today?->morning_time_in, 'morning_time_out' => $today?->morning_time_out,
                'afternoon_time_in' => $today?->afternoon_time_in, 'afternoon_time_out' => $today?->afternoon_time_out,
                'overtime_time_in' => $today?->overtime_time_in, 'overtime_time_out' => $today?->overtime_time_out, 'overtime_minutes' => (int) ($today?->overtime_minutes ?? 0),
                'status_morning' => $today?->status_morning, 'status_afternoon' => $today?->status_afternoon,
                'total_hours' => $today?->total_hours !== null ? (float) $today->total_hours : null,
                'checked_in' => (bool) $firstIn,
                'on_duty' => ((bool) $latestIn && ! $latestOut) || ($today?->overtime_time_in && ! $today?->overtime_time_out),
            ],
            'pending_inventory_requests' => DB::table('inventory_requests')->where('employee_id', $employee->id)->where('status', 'pending')->count(),
            // Approved installation requests that still wait for the employee's installation report.
            'awaiting_installation_reports' => Installations::awaitingReport((int) $employee->id),
        ]);
    }

    public function napBoxes(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $filter = (string) $request->query('filter', 'all');
        $filter = in_array($filter, ['all', 'available', 'full'], true) ? $filter : 'all';

        return response()->json(['success' => true, 'data' => NapBoxes::boxes(
            (string) $employee->branch_name,
            trim((string) $request->query('barangay', '')) ?: null,
            trim((string) $request->query('q', '')),
            $filter,
        )]);
    }

    public function napBox(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $box = NapBoxes::box((int) $request->query('nap_box_id'), (string) $employee->branch_name);

        return $box ? response()->json(['success' => true, 'data' => $box]) : response()->json(['success' => false, 'message' => 'That NAP box was not found.'], 404);
    }

    /** The photo of a NAP box of the employee's branch (the image itself, not JSON); the box says whether it has one. */
    public function napBoxPhoto(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $employee = $this->employee($request);

        return NapBoxes::photoResponse((int) $request->query('nap_box_id'), (string) $employee->branch_name);
    }

    // ---- history ----------------------------------------------------------------------------------

    /** Item out and item return of the employee, newest first. */
    public function transactions(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $type = (string) $request->query('type', 'all');
        $items = [];

        if (in_array($type, ['all', 'inventory'], true)) {
            $rows = DB::table('inventory_requests')->where('employee_id', $employee->id)->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();
            // Who approved or denied a request, so the app can say so.
            $deciders = DB::table('users')->whereIn('id', $rows->pluck('approved_by')->filter()->unique()->all())->pluck('full_name', 'id');
            foreach ($rows as $row) {
                $isReturn = ($row->request_type ?? 'out') === 'return';
                $detail = array_filter([
                    'Qty ' . $row->quantity,
                    $isReturn ? ($row->item_condition ? 'Condition: ' . $row->item_condition : null) : ($row->area_used ? 'Used for: ' . $row->area_used : null),
                    $row->person_in_charge ? 'In charge: ' . $row->person_in_charge : null,
                ]);
                $items[] = [
                    'kind' => $isReturn ? 'item_return' : 'item_out', 'id' => (int) $row->id, 'code' => '#' . $row->id,
                    'title' => $isReturn ? 'Item Return' : 'Item Out', 'subtitle' => $row->item_name, 'detail' => implode(' • ', $detail),
                    'status' => $row->status, 'status_label' => ucfirst($row->status), 'occurred_at' => $row->created_at,
                    'decided_at' => $row->status === 'pending' ? null : $row->approved_at,
                    'decided_by' => $row->status === 'pending' ? null : ($deciders[$row->approved_by] ?? null),
                ];
            }
        }

        if (in_array($type, ['all', 'installation'], true)) {
            foreach (Installations::reports((string) $employee->branch_name, (int) $employee->id, null, 100) as $report) {
                $items[] = [
                    'kind' => 'installation', 'id' => $report['id'], 'code' => $report['code'],
                    'title' => 'Client Installation', 'subtitle' => $report['client_name'],
                    'detail' => implode(' • ', array_filter([$report['nap_box_name'] ? $report['nap_box_name'] . ' port ' . $report['port_number'] : null, $report['barangay_name']])),
                    'status' => $report['status'] === 'submitted' ? 'pending' : ($report['status'] === 'confirmed' ? 'approved' : 'denied'),
                    'status_label' => $report['status_label'], 'occurred_at' => $report['submitted_at'],
                ];
            }
        }

        usort($items, fn (array $a, array $b) => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));

        return response()->json(['success' => true, 'data' => array_slice($items, 0, 100)]);
    }

    // ---- helpers ----------------------------------------------------------------------------------

    private function employee(Request $request): object
    {
        return $request->attributes->get('employee');
    }
}

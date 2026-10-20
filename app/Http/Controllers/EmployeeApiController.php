<?php

namespace App\Http\Controllers;

use App\Support\NapBoxes;
use App\Support\WorkRequestService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What the employee app needs besides sign in, attendance and stock (those are in ApiController): the home
 * screen, clients, barangays, NAP boxes, installation and repair requests and the transaction history.
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
            'work_requests' => WorkRequestService::counts((int) $employee->id, null),
            'pending_inventory_requests' => DB::table('inventory_requests')->where('employee_id', $employee->id)->where('status', 'pending')->count(),
        ]);
    }

    /** The barangays of the employee's branch, for the work request forms. */
    public function barangays(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json(['success' => true, 'data' => NapBoxes::barangaysOf((string) $employee->branch_name)]);
    }

    /** Clients of the employee's branch, for choosing an existing client. */
    public function clientsSearch(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $search = trim((string) $request->query('q', ''));
        $barangay = trim((string) $request->query('barangay', ''));

        $clients = DB::table('clients')
            ->leftJoin('nap_ports', 'nap_ports.client_id', '=', 'clients.id')
            ->leftJoin('nap_boxes', 'nap_boxes.id', '=', 'nap_ports.nap_box_id')
            ->whereRaw('LOWER(clients.branch_name) = LOWER(?)', [(string) $employee->branch_name])
            ->where('clients.archived', 0)
            ->when($barangay !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(clients.registered_barangay_name, '')) = LOWER(?)", [$barangay]))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                // LOWER() on both sides: LIKE ignores the case on MySQL but not on Postgres (Supabase).
                $inner->whereRaw('LOWER(clients.full_name) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(clients.client_code) LIKE LOWER(?)', [$like])
                    ->orWhereRaw("LOWER(COALESCE(clients.contact_number, '')) LIKE LOWER(?)", [$like]);
            }))
            ->orderBy('clients.full_name')
            ->limit(25)
            ->get(['clients.id', 'clients.client_code', 'clients.full_name', 'clients.contact_number', 'clients.registered_barangay_name', 'clients.exact_address', 'clients.status', 'nap_boxes.name as nap_box_name', 'nap_ports.port_number']);

        return response()->json(['success' => true, 'data' => $clients->map(fn ($client) => [
            'id' => (int) $client->id, 'client_code' => $client->client_code, 'full_name' => $client->full_name,
            'contact_number' => $client->contact_number, 'barangay' => $client->registered_barangay_name, 'address' => $client->exact_address,
            'status' => $client->status, 'nap_box_name' => $client->nap_box_name, 'nap_port_number' => $client->port_number ? (int) $client->port_number : null,
        ])->all()]);
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

    // ---- work requests ----------------------------------------------------------------------------

    public function workRequests(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $status = (string) $request->query('status', '');
        $statuses = $status === 'open'
            ? NapBoxes::OPEN_REQUEST_STATUSES
            : ($status !== '' && isset(WorkRequestService::STATUS_LABELS[$status]) ? [$status] : null);

        return response()->json(['success' => true, 'data' => WorkRequestService::listing((int) $employee->id, null, $statuses)]);
    }

    public function workRequest(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $detail = WorkRequestService::detail((int) $request->query('id'), (int) $employee->id);

        return $detail ? response()->json(['success' => true, 'data' => $detail]) : response()->json(['success' => false, 'message' => 'That request was not found.'], 404);
    }

    public function workRequestCreate(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return $this->attempt(function () use ($employee, $request): JsonResponse {
            $id = WorkRequestService::create($employee, $request->all());

            return response()->json(['success' => true, 'message' => 'Request submitted. It is now waiting for approval.', 'data' => WorkRequestService::detail($id, (int) $employee->id)], 201);
        });
    }

    public function workRequestUpdate(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $id = (int) $request->input('id');

        return $this->attempt(function () use ($employee, $request, $id): JsonResponse {
            WorkRequestService::resubmit($employee, $id, $request->all());

            return response()->json(['success' => true, 'message' => 'Request sent back for approval.', 'data' => WorkRequestService::detail($id, (int) $employee->id)]);
        });
    }

    public function workRequestCancel(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $id = (int) $request->input('id');

        return $this->attempt(function () use ($employee, $id): JsonResponse {
            WorkRequestService::cancel($employee, $id);

            return response()->json(['success' => true, 'message' => 'Request cancelled.', 'data' => WorkRequestService::detail($id, (int) $employee->id)]);
        });
    }

    public function workRequestComplete(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $id = (int) $request->input('id');

        return $this->attempt(function () use ($employee, $request, $id): JsonResponse {
            WorkRequestService::complete($employee, $id, $request->all());

            return response()->json(['success' => true, 'message' => 'Completion report submitted. The admin will confirm it.', 'data' => WorkRequestService::detail($id, (int) $employee->id)]);
        });
    }

    // ---- history ----------------------------------------------------------------------------------

    /** Item out, item return, installation and repair of the employee, newest first. */
    public function transactions(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $type = (string) $request->query('type', 'all');
        $items = [];

        if (in_array($type, ['all', 'inventory'], true)) {
            $rows = DB::table('inventory_requests')->where('employee_id', $employee->id)->orderByDesc('created_at')->limit(100)->get();
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
                ];
            }
        }

        if (in_array($type, ['all', 'installation', 'repair'], true)) {
            $statuses = null;
            foreach (WorkRequestService::listing((int) $employee->id, null, $statuses, 100) as $work) {
                if (in_array($type, ['installation', 'repair'], true) && $work['work_type'] !== $type) {
                    continue;
                }
                $where = $work['work_type'] === 'installation'
                    ? trim(($work['actual_nap_box_name'] ?: $work['nap_box_name'] ?: '') . ' port ' . ($work['actual_nap_port_number'] ?: $work['nap_port_number'] ?: ''), ' ')
                    : (string) $work['problem_details'];
                $items[] = [
                    'kind' => $work['work_type'], 'id' => $work['id'], 'code' => $work['request_code'],
                    'title' => ucfirst($work['work_type']), 'subtitle' => (string) $work['client_name'],
                    'detail' => trim($work['barangay_name'] . ' • ' . $where, ' •'),
                    'status' => $work['status'], 'status_label' => $work['status_label'], 'occurred_at' => $work['created_at'],
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

    /** Run an action and turn a broken rule into a message the app can show. */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}

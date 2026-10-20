<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Installation and repair requests from the employee app.
 *
 *   employee submits  -> pending_approval
 *   admin approves    -> approved           (or returns it: returned -> employee corrects and resubmits,
 *                                            or rejects it: rejected)
 *   employee reports  -> work_completed     (actual NAP box and port, notes, materials used / returned / damaged)
 *   admin confirms    -> confirmed          (the port becomes occupied, a new client is saved, stock is finalised)
 *                        or goes back to approved so the report can be corrected.
 *
 * Every rule that is broken is reported as a DomainException with a message the person can act on.
 */
final class WorkRequestService
{
    public const TYPES = ['installation', 'repair'];

    public const STATUS_LABELS = [
        'pending_approval' => 'Pending Approval',
        'returned' => 'Returned for Correction',
        'approved' => 'Approved',
        'work_completed' => 'Work Completed',
        'confirmed' => 'Confirmed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    // ---- the employee's side ----------------------------------------------------------------------

    /** Submit a new request. Returns its id. */
    public static function create(object $employee, array $input): int
    {
        return DB::transaction(function () use ($employee, $input): int {
            $fields = self::normalize($employee, $input, null);

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = 'WR-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
                if (! DB::table('work_requests')->where('request_code', $code)->exists()) {
                    break;
                }
            }

            return (int) DB::table('work_requests')->insertGetId($fields + [
                'request_code' => $code,
                'branch_name' => $employee->branch_name,
                'employee_id' => $employee->id,
                'status' => 'pending_approval',
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Correct a request the admin returned and send it back for approval. */
    public static function resubmit(object $employee, int $id, array $input): void
    {
        DB::transaction(function () use ($employee, $id, $input): void {
            $request = self::ownRequest($employee, $id);
            if ($request->status !== 'returned') {
                throw new DomainException('Only a request that was returned for correction can be edited.');
            }
            $fields = self::normalize($employee, $input, $id);
            DB::table('work_requests')->where('id', $id)->update($fields + ['status' => 'pending_approval', 'submitted_at' => now(), 'updated_at' => now()]);
        });
    }

    public static function cancel(object $employee, int $id): void
    {
        DB::transaction(function () use ($employee, $id): void {
            $request = self::ownRequest($employee, $id);
            if (! in_array($request->status, ['pending_approval', 'returned'], true)) {
                throw new DomainException('Only a request that is waiting for approval or was returned can be cancelled.');
            }
            DB::table('work_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
    }

    /** The completion report: what was really done, the port really used and the materials. */
    public static function complete(object $employee, int $id, array $input): void
    {
        DB::transaction(function () use ($employee, $id, $input): void {
            $request = self::ownRequest($employee, $id);
            if ($request->status !== 'approved') {
                throw new DomainException('A completion report can only be sent for an approved request.');
            }

            $notes = trim((string) ($input['completion_notes'] ?? ''));
            if (mb_strlen($notes) < 3) {
                throw new DomainException($request->work_type === 'repair' ? 'Describe the repair that was done.' : 'Add a note about the installation.');
            }

            $actualBox = null;
            $actualPort = null;
            if ($request->work_type === 'installation') {
                $actualBox = (int) ($input['actual_nap_box_id'] ?? 0);
                $actualPort = (int) ($input['actual_nap_port_id'] ?? 0);
                if (! $actualBox || ! $actualPort) {
                    throw new DomainException('Choose the NAP box and port that were really used.');
                }
                self::lockPort($actualPort);
                $problem = NapBoxes::portProblem($actualPort, $actualBox, $request->branch_name, $id, $request->client_id ? (int) $request->client_id : null);
                if ($problem) {
                    throw new DomainException($problem);
                }
            }

            $materials = self::materials($request->branch_name, $input['materials'] ?? []);
            DB::table('work_request_materials')->where('work_request_id', $id)->delete();
            foreach ($materials as $material) {
                DB::table('work_request_materials')->insert($material + ['work_request_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('work_requests')->where('id', $id)->update([
                'actual_nap_box_id' => $actualBox, 'actual_nap_port_id' => $actualPort, 'completion_notes' => $notes,
                'status' => 'work_completed', 'completed_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    // ---- the admin's side -------------------------------------------------------------------------

    public static function approve(int $id, int $adminId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($id, $adminId, $remarks): void {
            $request = self::lockedRequest($id);
            if ($request->status !== 'pending_approval') {
                throw new DomainException('Only a request that is pending approval can be approved.');
            }
            if ($request->work_type === 'installation') {
                self::lockPort((int) $request->nap_port_id);
                $problem = NapBoxes::portProblem((int) $request->nap_port_id, (int) $request->nap_box_id, $request->branch_name, $id, $request->client_id ? (int) $request->client_id : null);
                if ($problem) {
                    throw new DomainException($problem . ' Return the request so the employee can pick another port.');
                }
            }
            self::review($id, 'approved', $adminId, self::cleanRemarks($remarks));
        });
    }

    public static function returnForCorrection(int $id, int $adminId, string $remarks): void
    {
        self::decide($id, $adminId, $remarks, 'pending_approval', 'returned', 'Only a request that is pending approval can be returned.');
    }

    public static function reject(int $id, int $adminId, string $remarks): void
    {
        self::decide($id, $adminId, $remarks, 'pending_approval', 'rejected', 'Only a request that is pending approval can be rejected.');
    }

    /** The report is wrong: send it back to the employee, the request stays approved. */
    public static function returnCompletion(int $id, int $adminId, string $remarks): void
    {
        DB::transaction(function () use ($id, $adminId, $remarks): void {
            $request = self::lockedRequest($id);
            if ($request->status !== 'work_completed') {
                throw new DomainException('Only a completion report that is waiting for confirmation can be returned.');
            }
            $remarks = self::requiredRemarks($remarks);
            DB::table('work_requests')->where('id', $id)->update([
                'status' => 'approved', 'admin_remarks' => $remarks, 'completed_at' => null, 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /**
     * Confirm the finished work and update everything that follows from it. $clientInput is only used for a
     * client who is not in the database yet: internet_plan_id (required), installation_date and email.
     */
    public static function confirm(int $id, int $adminId, array $clientInput = [], ?string $remarks = null): void
    {
        DB::transaction(function () use ($id, $adminId, $clientInput, $remarks): void {
            $request = self::lockedRequest($id);
            if ($request->status !== 'work_completed') {
                throw new DomainException('Only a completed job that is waiting for confirmation can be confirmed.');
            }

            // 1. The client: a new one is saved now, with the plan the admin chooses.
            $clientId = $request->client_id ? (int) $request->client_id : null;
            if (! $clientId) {
                $clientId = self::createClient($request, $clientInput);
            }

            // 2. The port: an installation takes the port that was really used.
            if ($request->work_type === 'installation') {
                $portId = (int) $request->actual_nap_port_id;
                self::lockPort($portId);
                $problem = NapBoxes::portProblem($portId, (int) $request->actual_nap_box_id, $request->branch_name, $id, $clientId);
                if ($problem) {
                    throw new DomainException($problem . ' Return the report so the employee can correct the port.');
                }
                DB::table('nap_ports')->where('id', $portId)->update(['status' => 'occupied', 'client_id' => $clientId, 'occupied_at' => now(), 'updated_at' => now()]);
            }

            // 3. The stock: finalise what the employee reported.
            self::finaliseMaterials($request, $adminId, $clientId);

            DB::table('work_requests')->where('id', $id)->update([
                'status' => 'confirmed', 'client_id' => $clientId, 'admin_remarks' => self::cleanRemarks($remarks) ?? $request->admin_remarks,
                'confirmed_by' => $adminId, 'confirmed_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    // ---- reading ----------------------------------------------------------------------------------

    /**
     * One request with everything the app or the admin page shows, or null when it is not in the scope.
     * $employeeId limits it to one employee's requests, $branch to one branch.
     */
    public static function detail(int $id, ?int $employeeId = null, ?string $branch = null): ?array
    {
        $row = self::baseQuery()
            ->where('work_requests.id', $id)
            ->when($employeeId, fn ($q) => $q->where('work_requests.employee_id', $employeeId))
            ->when($branch !== null, fn ($q) => $branch === '' ? $q->whereRaw('1 = 0') : $q->whereRaw('LOWER(work_requests.branch_name) = LOWER(?)', [$branch]))
            ->first();

        return $row ? self::present($row, true) : null;
    }

    /** A list of requests, newest first. */
    public static function listing(?int $employeeId, ?string $branch, ?array $statuses = null, int $limit = 100, ?string $barangay = null): array
    {
        $rows = self::baseQuery()
            ->when($employeeId, fn ($q) => $q->where('work_requests.employee_id', $employeeId))
            ->when($branch !== null, fn ($q) => $branch === '' ? $q->whereRaw('1 = 0') : $q->whereRaw('LOWER(work_requests.branch_name) = LOWER(?)', [$branch]))
            ->when($barangay !== null && $barangay !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(work_requests.barangay_name, '')) = LOWER(?)", [$barangay]))
            ->when($statuses, fn ($q) => $q->whereIn('work_requests.status', $statuses))
            ->orderByDesc('work_requests.created_at')
            ->orderByDesc('work_requests.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => self::present($row, false))->all();
    }

    public static function counts(?int $employeeId, ?string $branch, ?string $barangay = null): array
    {
        $query = DB::table('work_requests')
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($branch !== null, fn ($q) => $branch === '' ? $q->whereRaw('1 = 0') : $q->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch]))
            ->when($barangay !== null && $barangay !== '', fn ($q) => $q->whereRaw("LOWER(COALESCE(barangay_name, '')) = LOWER(?)", [$barangay]))
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];
        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $counts[$status] = (int) ($query[$status] ?? 0);
        }

        return $counts;
    }

    // ---- internals --------------------------------------------------------------------------------

    private static function baseQuery()
    {
        return DB::table('work_requests')
            ->join('employees', 'employees.id', '=', 'work_requests.employee_id')
            ->leftJoin('clients', 'clients.id', '=', 'work_requests.client_id')
            ->leftJoin('nap_boxes as box', 'box.id', '=', 'work_requests.nap_box_id')
            ->leftJoin('nap_ports as port', 'port.id', '=', 'work_requests.nap_port_id')
            ->leftJoin('nap_boxes as actual_box', 'actual_box.id', '=', 'work_requests.actual_nap_box_id')
            ->leftJoin('nap_ports as actual_port', 'actual_port.id', '=', 'work_requests.actual_nap_port_id')
            ->select('work_requests.*', 'employees.full_name as employee_name', 'employees.position as employee_position',
                'clients.full_name as client_name', 'clients.client_code', 'clients.contact_number as client_phone', 'clients.exact_address as client_address',
                'box.name as nap_box_name', 'port.port_number as nap_port_number',
                'actual_box.name as actual_nap_box_name', 'actual_port.port_number as actual_nap_port_number');
    }

    private static function present(object $row, bool $withMaterials): array
    {
        $isNew = ! $row->client_id;
        $data = [
            'id' => (int) $row->id,
            'request_code' => $row->request_code,
            'branch_name' => $row->branch_name,
            'work_type' => $row->work_type,
            'status' => $row->status,
            'status_label' => self::STATUS_LABELS[$row->status] ?? ucfirst(str_replace('_', ' ', $row->status)),
            'employee_id' => (int) $row->employee_id,
            'employee_name' => $row->employee_name,
            'employee_position' => $row->employee_position,
            'client_id' => $row->client_id ? (int) $row->client_id : null,
            'is_new_client' => $isNew,
            'client_name' => $isNew ? $row->new_client_name : $row->client_name,
            'client_code' => $row->client_code,
            'client_phone' => $isNew ? $row->new_client_phone : $row->client_phone,
            'client_address' => $isNew ? $row->new_client_address : $row->client_address,
            'barangay_name' => $row->barangay_name,
            'location' => $row->location,
            'problem_details' => $row->problem_details,
            'nap_box_id' => $row->nap_box_id ? (int) $row->nap_box_id : null,
            'nap_box_name' => $row->nap_box_name,
            'nap_port_id' => $row->nap_port_id ? (int) $row->nap_port_id : null,
            'nap_port_number' => $row->nap_port_number ? (int) $row->nap_port_number : null,
            'actual_nap_box_id' => $row->actual_nap_box_id ? (int) $row->actual_nap_box_id : null,
            'actual_nap_box_name' => $row->actual_nap_box_name,
            'actual_nap_port_id' => $row->actual_nap_port_id ? (int) $row->actual_nap_port_id : null,
            'actual_nap_port_number' => $row->actual_nap_port_number ? (int) $row->actual_nap_port_number : null,
            'completion_notes' => $row->completion_notes,
            'admin_remarks' => $row->admin_remarks,
            'submitted_at' => $row->submitted_at,
            'reviewed_at' => $row->reviewed_at,
            'completed_at' => $row->completed_at,
            'confirmed_at' => $row->confirmed_at,
            'created_at' => $row->created_at,
        ];

        if ($withMaterials) {
            $data['materials'] = DB::table('work_request_materials')->where('work_request_id', $row->id)->orderBy('id')
                ->get(['inventory_id', 'item_name', 'used_quantity', 'returned_quantity', 'damaged_quantity'])
                ->map(fn ($m) => [
                    'inventory_id' => $m->inventory_id ? (int) $m->inventory_id : null,
                    'item_name' => $m->item_name,
                    'used_quantity' => (int) $m->used_quantity,
                    'returned_quantity' => (int) $m->returned_quantity,
                    'damaged_quantity' => (int) $m->damaged_quantity,
                ])->all();
        }

        return $data;
    }

    private static function ownRequest(object $employee, int $id): object
    {
        $request = DB::table('work_requests')->where('id', $id)->where('employee_id', $employee->id)->lockForUpdate()->first();
        if (! $request) {
            throw new DomainException('That request was not found.');
        }

        return $request;
    }

    private static function lockedRequest(int $id): object
    {
        $request = DB::table('work_requests')->where('id', $id)->lockForUpdate()->first();
        if (! $request) {
            throw new DomainException('That request was not found.');
        }

        return $request;
    }

    /** Serialise two people picking the same port. */
    private static function lockPort(int $portId): void
    {
        if ($portId > 0) {
            DB::table('nap_ports')->where('id', $portId)->lockForUpdate()->first();
        }
    }

    private static function decide(int $id, int $adminId, string $remarks, string $from, string $to, string $wrongState): void
    {
        DB::transaction(function () use ($id, $adminId, $remarks, $from, $to, $wrongState): void {
            $request = self::lockedRequest($id);
            if ($request->status !== $from) {
                throw new DomainException($wrongState);
            }
            self::review($id, $to, $adminId, self::requiredRemarks($remarks));
        });
    }

    private static function review(int $id, string $status, int $adminId, ?string $remarks): void
    {
        DB::table('work_requests')->where('id', $id)->update([
            'status' => $status, 'admin_remarks' => $remarks, 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'updated_at' => now(),
        ]);
    }

    private static function cleanRemarks(?string $remarks): ?string
    {
        $remarks = trim((string) $remarks);

        return $remarks === '' ? null : mb_substr($remarks, 0, 1000);
    }

    private static function requiredRemarks(string $remarks): string
    {
        $remarks = self::cleanRemarks($remarks);
        if ($remarks === null || mb_strlen($remarks) < 3) {
            throw new DomainException('Write what the employee has to correct.');
        }

        return $remarks;
    }

    /** Check the fields of a new or corrected request and return the columns to save. */
    private static function normalize(object $employee, array $input, ?int $exceptRequestId): array
    {
        $branch = trim((string) $employee->branch_name);
        $type = (string) ($input['work_type'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            throw new DomainException('Choose Installation or Repair.');
        }

        // The client: an existing one of this branch, or (installation only) the details of a new one.
        $clientId = (int) ($input['client_id'] ?? 0) ?: null;
        $newClient = is_array($input['new_client'] ?? null) ? $input['new_client'] : null;
        $client = null;
        if ($clientId) {
            $client = DB::table('clients')->where('id', $clientId)->first();
            if (! $client || $client->archived || strcasecmp(trim((string) $client->branch_name), $branch) !== 0) {
                throw new DomainException('Choose a client of your branch.');
            }
        }
        if ($type === 'repair' && ! $client) {
            throw new DomainException('A repair is for an existing client. Choose the client.');
        }
        if ($type === 'installation' && ! $client && ! $newClient) {
            throw new DomainException('Choose an existing client or enter the details of the new client.');
        }
        if ($client && $newClient) {
            throw new DomainException('Choose either an existing client or a new client, not both.');
        }

        $fields = [
            'work_type' => $type, 'client_id' => $client?->id,
            'new_client_name' => null, 'new_client_phone' => null, 'new_client_address' => null,
            'barangay_name' => null, 'location' => null, 'problem_details' => null,
            'nap_box_id' => null, 'nap_port_id' => null,
        ];

        if ($newClient) {
            $name = trim((string) ($newClient['name'] ?? ''));
            $phone = trim((string) ($newClient['phone'] ?? ''));
            $address = trim((string) ($newClient['address'] ?? ''));
            if (mb_strlen($name) < 2) {
                throw new DomainException('Enter the full name of the new client.');
            }
            if (! preg_match('/^\d{11}$/', $phone)) {
                throw new DomainException('Mobile number must contain exactly 11 digits.');
            }
            if (mb_strlen($address) < 3) {
                throw new DomainException('Enter the address of the new client.');
            }
            $fields['new_client_name'] = mb_substr($name, 0, 100);
            $fields['new_client_phone'] = $phone;
            $fields['new_client_address'] = $address;
        }

        $barangay = self::barangay($branch, (string) ($input['barangay'] ?? ''), $client);
        $fields['barangay_name'] = $barangay;

        if ($client) {
            $open = DB::table('work_requests')
                ->where('client_id', $client->id)->where('work_type', $type)
                ->whereIn('status', NapBoxes::OPEN_REQUEST_STATUSES)
                ->when($exceptRequestId, fn ($q) => $q->where('id', '!=', $exceptRequestId))
                ->value('request_code');
            if ($open) {
                throw new DomainException("{$client->full_name} already has an open {$type} request ({$open}).");
            }
        }

        if ($type === 'repair') {
            $problem = trim((string) ($input['problem_details'] ?? ''));
            if (mb_strlen($problem) < 3) {
                throw new DomainException('Describe the problem, for example no internet or a damaged cable.');
            }
            $fields['problem_details'] = mb_substr($problem, 0, 1000);
            $fields['location'] = mb_substr(trim((string) ($input['location'] ?? '')) ?: (string) ($client->exact_address ?? ''), 0, 500) ?: null;

            return $fields;
        }

        // Installation: where it will be connected.
        if (! $barangay) {
            throw new DomainException('Choose the barangay.');
        }
        $boxId = (int) ($input['nap_box_id'] ?? 0);
        $portId = (int) ($input['nap_port_id'] ?? 0);
        if (! $boxId || ! $portId) {
            throw new DomainException('Choose the NAP box and an available port.');
        }
        $box = DB::table('nap_boxes')->where('id', $boxId)->first();
        if (! $box || strcasecmp(trim((string) $box->barangay_name), $barangay) !== 0) {
            throw new DomainException("That NAP box is not in {$barangay}.");
        }
        if ($client && DB::table('nap_ports')->where('client_id', $client->id)->exists()) {
            throw new DomainException("{$client->full_name} is already connected to a NAP port. Send a repair request instead.");
        }
        self::lockPort($portId);
        $problem = NapBoxes::portProblem($portId, $boxId, $branch, $exceptRequestId, $client?->id);
        if ($problem) {
            throw new DomainException($problem);
        }
        $fields['nap_box_id'] = $boxId;
        $fields['nap_port_id'] = $portId;
        $fields['location'] = mb_substr(trim((string) ($input['location'] ?? '')), 0, 500) ?: null;

        return $fields;
    }

    /** The barangay as the branch spells it. An existing client brings theirs when none is typed. */
    private static function barangay(string $branch, string $typed, ?object $client): ?string
    {
        $typed = trim($typed);
        if ($typed === '' && $client) {
            $typed = trim((string) ($client->registered_barangay_name ?? ''));
        }
        if ($typed === '') {
            return null;
        }

        $options = NapBoxes::barangaysOf($branch);
        if ($options === []) {
            return $typed;
        }
        foreach ($options as $option) {
            if (strcasecmp($option, $typed) === 0) {
                return $option;
            }
        }

        throw new DomainException("{$typed} is not a barangay of {$branch}.");
    }

    /** The materials of a completion report: used, returned and damaged quantities of the branch's stock items. */
    private static function materials(string $branch, mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $materials = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $used = (int) ($row['used_quantity'] ?? $row['used'] ?? 0);
            $returned = (int) ($row['returned_quantity'] ?? $row['returned'] ?? 0);
            $damaged = (int) ($row['damaged_quantity'] ?? $row['damaged'] ?? 0);
            if ($used < 0 || $returned < 0 || $damaged < 0) {
                throw new DomainException('Quantities cannot be negative.');
            }
            if ($used + $returned + $damaged === 0) {
                continue;
            }

            $item = DB::table('inventory')->where('id', (int) ($row['inventory_id'] ?? 0))->first();
            if (! $item || strcasecmp(trim((string) $item->branch_name), trim($branch)) !== 0) {
                throw new DomainException('Choose materials from the stock of your branch.');
            }
            if (NapBoxes::isStockItem($item)) {
                throw new DomainException("{$item->item_name} is a NAP box, not a material. Your admin installs NAP boxes from the stock.");
            }
            if (isset($materials[$item->id])) {
                $materials[$item->id]['used_quantity'] += $used;
                $materials[$item->id]['returned_quantity'] += $returned;
                $materials[$item->id]['damaged_quantity'] += $damaged;
                continue;
            }
            $materials[$item->id] = ['inventory_id' => $item->id, 'item_name' => $item->item_name, 'used_quantity' => $used, 'returned_quantity' => $returned, 'damaged_quantity' => $damaged];
        }

        return array_values($materials);
    }

    /**
     * What was taken out was already deducted when the admin approved the item-out request, so used and damaged
     * items only leave a record in the stock history, while items brought back are added to the stock again.
     */
    private static function finaliseMaterials(object $request, int $adminId, ?int $clientId): void
    {
        // Who, for whom and where: each line of the stock history of this job points at the employee, the client and the NAP box.
        $links = [
            'employee_id' => (int) $request->employee_id, 'client_id' => $clientId, 'work_request_id' => (int) $request->id,
            'nap_box_id' => $request->work_type === 'installation' && $request->actual_nap_box_id ? (int) $request->actual_nap_box_id : null,
        ];
        $rows = DB::table('work_request_materials')->where('work_request_id', $request->id)->get();
        foreach ($rows as $row) {
            if (! $row->inventory_id) {
                continue;
            }
            $item = DB::table('inventory')->where('id', $row->inventory_id)->lockForUpdate()->first();
            if (! $item) {
                continue;
            }

            $balance = (int) $item->quantity;
            if ($row->returned_quantity > 0) {
                $balance += (int) $row->returned_quantity;
                DB::table('inventory')->where('id', $item->id)->update(['quantity' => $balance]);
                self::movement($item->id, (int) $row->returned_quantity, $balance, 'stock_returned', "Returned after {$request->work_type} {$request->request_code}.", $adminId, $links);
            }
            if ($row->used_quantity > 0) {
                self::movement($item->id, 0, $balance, 'job_used', "{$row->used_quantity} used on {$request->work_type} {$request->request_code}.", $adminId, $links);
            }
            if ($row->damaged_quantity > 0) {
                self::movement($item->id, 0, $balance, 'job_damaged', "{$row->damaged_quantity} damaged on {$request->work_type} {$request->request_code}.", $adminId, $links);
            }
        }
    }

    private static function movement(int $inventoryId, int $change, int $balance, string $type, string $remarks, int $adminId, array $links = []): void
    {
        StockMovements::record($inventoryId, $change, $balance, $type, $remarks, $adminId, $links);
    }

    /** Save the client of a finished installation and give them their first bill, like the Clients page does. */
    private static function createClient(object $request, array $input): int
    {
        $planId = (int) ($input['internet_plan_id'] ?? 0);
        $plan = DB::table('internet_plans')->where('id', $planId)->where('status', 'active')->where('archived', 0)->first();
        if (! $plan) {
            throw new DomainException('Choose the internet plan of the new client.');
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Please enter a valid email address.');
        }
        $date = trim((string) ($input['installation_date'] ?? '')) ?: now()->toDateString();
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('Please enter a valid installation date.');
        }

        $barangayId = null;
        if ($request->branch_name === 'Balilihan' && $request->barangay_name) {
            $barangayId = DB::table('barangays')->where('barangay_name', $request->barangay_name)->value('id');
        }

        $clientId = (int) DB::table('clients')->insertGetId([
            'client_code' => 'JFT-' . date('Y') . '-' . strtoupper(uniqid()),
            'full_name' => $request->new_client_name,
            'contact_number' => $request->new_client_phone,
            'email' => $email === '' ? null : $email,
            'branch_name' => $request->branch_name,
            'registered_barangay_name' => $request->barangay_name,
            'barangay_id' => $barangayId,
            'exact_address' => $request->new_client_address,
            'wifi_plan' => $plan->plan_name,
            'internet_plan_id' => $plan->id,
            'monthly_rate' => $plan->monthly_price,
            'installation_fee' => $plan->installation_fee,
            'installation_date' => $date,
            'status' => 'active',
        ]);

        $billingStart = date('Y-m-d');
        $due = date('Y-m-d', strtotime($billingStart . ' +30 days'));
        DB::table('bills')->insert([
            'client_id' => $clientId, 'bill_number' => 'BILL-' . date('Ymd') . '-' . strtoupper(uniqid()),
            'billing_period_start' => $billingStart, 'billing_period_end' => $due, 'amount' => $plan->monthly_price, 'due_date' => $due, 'status' => 'pending',
        ]);

        return $clientId;
    }
}

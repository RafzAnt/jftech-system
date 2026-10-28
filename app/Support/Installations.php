<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Installing a client from the employee app.
 *
 *   1. The employee requests the items to take (a normal item-out request); the branch admin approves it, which releases the stock.
 *   2. After installing, the employee files an installation report: where it was installed (coordinates), the NAP box and port,
 *      the client's details (name, address, contact, plan, mode of payment, IP address, Facebook account) and the items brought back.
 *      The NAP port is reserved for the report, but the client does not exist yet.
 *   3. The branch admin checks the report, may edit any detail and confirms it: the client is added (with its first bill), the port is
 *      connected, the items brought back go into stock and what was used is recorded against the client and NAP box.
 *      The admin can also reject it, which frees the items so the employee can report again.
 */
final class Installations
{
    public const CONDITIONS = ['Good', 'Damaged', 'Defective'];

    private const LABELS = ['submitted' => 'Waiting for admin', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst($status);
    }

    // ---- what the employee app needs ------------------------------------------------------------------

    /** The approved item-out requests for an installation that no report has used yet (what the employee took). */
    public static function taken(int $employeeId): array
    {
        return DB::table('inventory_requests')
            ->where('employee_id', $employeeId)->where('status', 'approved')->where('request_type', 'out')->whereNull('installation_id')
            ->whereRaw("LOWER(COALESCE(area_used, '')) = 'installation'")
            ->orderByDesc('approved_at')->orderByDesc('id')->limit(50)
            ->get(['id', 'item_name', 'quantity', 'approved_at', 'created_at'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id, 'item_name' => $row->item_name, 'quantity' => (int) $row->quantity,
                'approved_at' => (string) ($row->approved_at ?? $row->created_at),
            ])->all();
    }

    /** How many approved installation requests still wait for the employee's report. */
    public static function awaitingReport(int $employeeId): int
    {
        return DB::table('inventory_requests')
            ->where('employee_id', $employeeId)->where('status', 'approved')->where('request_type', 'out')->whereNull('installation_id')
            ->whereRaw("LOWER(COALESCE(area_used, '')) = 'installation'")->count();
    }

    /** What the report form offers: the barangays of the branch, the plans, the modes of payment and the items taken. */
    public static function options(object $employee): array
    {
        $plans = DB::table('internet_plans')->where('status', 'active')->where('archived', 0)->orderBy('monthly_price')
            ->get(['id', 'plan_name', 'speed_mbps', 'monthly_price', 'installation_fee'])
            ->map(fn ($plan): array => [
                'id' => (int) $plan->id, 'plan_name' => $plan->plan_name, 'speed_mbps' => (int) $plan->speed_mbps,
                'monthly_price' => (float) $plan->monthly_price, 'installation_fee' => (float) $plan->installation_fee,
            ])->all();

        $modes = [];
        foreach (ClientRegistration::PAYMENT_MODES as $value => $label) {
            $modes[] = ['value' => $value, 'label' => $label];
        }

        return [
            'barangays' => NapBoxes::barangaysOf((string) $employee->branch_name),
            'plans' => $plans,
            'payment_modes' => $modes,
            'conditions' => self::CONDITIONS,
            'taken' => self::taken((int) $employee->id),
        ];
    }

    /** Save the report of an employee. Throws a DomainException with a message to show the person. Returns the report id. */
    public static function submit(object $employee, array $input): int
    {
        $branch = (string) $employee->branch_name;
        // The client does not exist yet, so only its details are checked here; the port is locked in the transaction.
        $data = ClientRegistration::clean($input, $branch, false, true);
        $requestIds = array_values(array_unique(array_map('intval', (array) ($input['request_ids'] ?? []))));
        $returns = self::cleanReturns((array) ($input['returns'] ?? []), $branch);

        return DB::transaction(function () use ($employee, $branch, $data, $input, $requestIds, $returns): int {
            $port = ClientRegistration::lockPort((int) $data['nap_port_id'], $branch);

            $id = (int) DB::table('client_installations')->insertGetId([
                'branch_name' => $branch, 'employee_id' => $employee->id, 'status' => 'submitted',
                'client_name' => $data['full_name'], 'contact_number' => $data['contact_number'], 'email' => $data['email'],
                'barangay_name' => $data['barangay_name'], 'exact_address' => $data['exact_address'], 'internet_plan_id' => $data['plan']->id,
                'payment_mode' => $data['payment_mode'], 'ip_address' => $data['ip_address'], 'facebook_account' => $data['facebook_account'],
                'installation_date' => $data['installation_date'], 'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
                'nap_box_id' => $port->box_id, 'nap_port_id' => $port->id,
                'remarks' => trim((string) ($input['remarks'] ?? '')) ?: null,
                'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($requestIds !== []) {
                $linked = DB::table('inventory_requests')->whereIn('id', $requestIds)
                    ->where('employee_id', $employee->id)->where('status', 'approved')->where('request_type', 'out')->whereNull('installation_id')
                    ->lockForUpdate()->pluck('id')->all();
                if (count($linked) !== count($requestIds)) {
                    throw new DomainException('Some of the items you chose were already reported or are not approved. Refresh the list and try again.');
                }
                DB::table('inventory_requests')->whereIn('id', $requestIds)->update(['installation_id' => $id, 'updated_at' => now()]);
            }

            foreach ($returns as $line) {
                DB::table('client_installation_returns')->insert($line + ['installation_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
    }

    /**
     * The items brought back, checked: an item of the branch, not a NAP box, 1 or more, with its condition.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function cleanReturns(array $lines, string $branch): array
    {
        $result = [];
        foreach ($lines as $line) {
            $line = (array) $line;
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($quantity < 1) {
                continue;
            }
            $item = DB::table('inventory')->where('id', (int) ($line['inventory_id'] ?? 0))->first();
            if (! $item || strcasecmp(trim((string) $item->branch_name), trim($branch)) !== 0) {
                throw new DomainException('One of the items you brought back is not in the stock of your branch. Refresh the list and try again.');
            }
            if (NapBoxes::isStockItem($item)) {
                throw new DomainException("{$item->item_name} is a NAP box. NAP boxes are installed by your admin, so they cannot be brought back here.");
            }
            $condition = (string) ($line['item_condition'] ?? 'Good');
            $result[] = [
                'inventory_id' => (int) $item->id, 'item_name' => $item->item_name, 'quantity' => $quantity,
                'item_condition' => in_array($condition, self::CONDITIONS, true) ? $condition : 'Good',
            ];
        }

        return $result;
    }

    // ---- reading reports ------------------------------------------------------------------------------

    /**
     * Reports with everything shown on screen. $branch: null is every branch, '' none. $employeeId limits to one employee
     * (the app); $status limits to one status.
     */
    public static function reports(?string $branch, ?int $employeeId = null, ?string $status = null, int $limit = 100, ?int $id = null): array
    {
        $rows = DB::table('client_installations as ci')
            ->leftJoin('employees as emp', 'emp.id', '=', 'ci.employee_id')
            ->leftJoin('nap_boxes as nb', 'nb.id', '=', 'ci.nap_box_id')
            ->leftJoin('nap_ports as np', 'np.id', '=', 'ci.nap_port_id')
            ->leftJoin('internet_plans as ip', 'ip.id', '=', 'ci.internet_plan_id')
            ->leftJoin('clients as cl', 'cl.id', '=', 'ci.client_id')
            ->leftJoin('users as rv', 'rv.id', '=', 'ci.reviewed_by')
            ->when($branch === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(ci.branch_name) = LOWER(?)', [$branch]))
            ->when($employeeId !== null, fn ($q) => $q->where('ci.employee_id', $employeeId))
            ->when($status !== null && $status !== 'all', fn ($q) => $q->where('ci.status', $status))
            ->when($id !== null, fn ($q) => $q->where('ci.id', $id))
            ->orderByDesc('ci.submitted_at')->orderByDesc('ci.id')
            ->limit($limit)
            ->get([
                'ci.*', 'emp.full_name as employee_name', 'emp.employee_code', 'nb.name as nap_box_name', 'np.port_number',
                'ip.plan_name', 'ip.monthly_price', 'cl.client_code', 'rv.full_name as reviewer_name',
            ]);
        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->all();
        $taken = DB::table('inventory_requests')->whereIn('installation_id', $ids)->orderBy('id')
            ->get(['id', 'installation_id', 'inventory_id', 'item_name', 'quantity'])->groupBy('installation_id');
        $returns = DB::table('client_installation_returns')->whereIn('installation_id', $ids)->orderBy('id')
            ->get(['id', 'installation_id', 'inventory_id', 'item_name', 'quantity', 'accepted_quantity', 'item_condition'])->groupBy('installation_id');

        return $rows->map(function ($row) use ($taken, $returns): array {
            return [
                'id' => (int) $row->id,
                'code' => '#' . $row->id,
                'status' => $row->status,
                'status_label' => self::label($row->status),
                'branch_name' => $row->branch_name,
                'employee_id' => (int) $row->employee_id,
                'employee_name' => $row->employee_name,
                'client_name' => $row->client_name,
                'contact_number' => $row->contact_number,
                'email' => $row->email,
                'barangay_name' => $row->barangay_name,
                'exact_address' => $row->exact_address,
                'internet_plan_id' => $row->internet_plan_id ? (int) $row->internet_plan_id : null,
                'plan_name' => $row->plan_name,
                'monthly_price' => $row->monthly_price !== null ? (float) $row->monthly_price : null,
                'payment_mode' => $row->payment_mode,
                'payment_mode_label' => ClientRegistration::PAYMENT_MODES[$row->payment_mode] ?? $row->payment_mode,
                'ip_address' => $row->ip_address,
                'facebook_account' => $row->facebook_account,
                'installation_date' => (string) $row->installation_date,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'coordinates' => Coordinates::format($row->latitude, $row->longitude),
                'map_url' => Coordinates::mapUrl($row->latitude, $row->longitude),
                'nap_box_id' => $row->nap_box_id ? (int) $row->nap_box_id : null,
                'nap_port_id' => $row->nap_port_id ? (int) $row->nap_port_id : null,
                'nap_box_name' => $row->nap_box_name,
                'port_number' => $row->port_number !== null ? (int) $row->port_number : null,
                'remarks' => $row->remarks,
                'admin_remarks' => $row->admin_remarks,
                'client_id' => $row->client_id ? (int) $row->client_id : null,
                'client_code' => $row->client_code,
                'submitted_at' => (string) ($row->submitted_at ?? $row->created_at),
                'reviewed_at' => $row->reviewed_at ? (string) $row->reviewed_at : null,
                'reviewer_name' => $row->reviewer_name,
                'taken' => ($taken[$row->id] ?? collect())->map(fn ($t): array => ['id' => (int) $t->id, 'item_name' => $t->item_name, 'quantity' => (int) $t->quantity])->values()->all(),
                'returns' => ($returns[$row->id] ?? collect())->map(fn ($r): array => [
                    'id' => (int) $r->id, 'item_name' => $r->item_name, 'quantity' => (int) $r->quantity,
                    'accepted_quantity' => $r->accepted_quantity !== null ? (int) $r->accepted_quantity : null, 'item_condition' => $r->item_condition,
                ])->values()->all(),
            ];
        })->all();
    }

    /** The number of reports by status for a branch (null is every branch). */
    public static function counts(?string $branch): array
    {
        $counts = DB::table('client_installations')
            ->when($branch === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch]))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return ['submitted' => (int) ($counts['submitted'] ?? 0), 'confirmed' => (int) ($counts['confirmed'] ?? 0), 'rejected' => (int) ($counts['rejected'] ?? 0)];
    }

    // ---- the admin's decision -------------------------------------------------------------------------

    /**
     * Confirm a report: the admin's (possibly edited) details become the client. $input holds the client fields of
     * ClientRegistration::clean plus accepted[<return line id>] = quantity that goes back into stock. Returns the client id.
     */
    public static function confirm(int $id, array $input, int $adminId, string $branch): int
    {
        return DB::transaction(function () use ($id, $input, $adminId, $branch): int {
            $report = self::lockSubmitted($id, $branch);
            $data = ClientRegistration::clean($input, $branch, false, true);
            $clientId = ClientRegistration::create($data, $id);
            $port = DB::table('nap_ports')->where('id', $data['nap_port_id'])->first();

            $employee = DB::table('employees')->where('id', $report->employee_id)->value('full_name');
            $links = ['employee_id' => (int) $report->employee_id, 'client_id' => $clientId, 'nap_box_id' => (int) $port->nap_box_id];

            // What was brought back and checked by the admin goes into stock.
            $acceptedByItem = [];
            $accepted = (array) ($input['accepted'] ?? []);
            foreach (DB::table('client_installation_returns')->where('installation_id', $id)->orderBy('id')->get() as $line) {
                $quantity = isset($accepted[$line->id]) ? max(0, min((int) $line->quantity, (int) $accepted[$line->id])) : (int) $line->quantity;
                DB::table('client_installation_returns')->where('id', $line->id)->update(['accepted_quantity' => $quantity, 'updated_at' => now()]);
                if ($quantity === 0 || ! $line->inventory_id) {
                    continue;
                }
                $item = DB::table('inventory')->where('id', $line->inventory_id)->lockForUpdate()->first();
                if (! $item) {
                    continue;
                }
                $balance = (int) $item->quantity + $quantity;
                DB::table('inventory')->where('id', $item->id)->update(['quantity' => $balance]);
                $condition = $line->item_condition ? " Condition: {$line->item_condition}." : '';
                StockMovements::record((int) $item->id, $quantity, $balance, 'stock_returned', "Brought back by {$employee} after installing {$data['full_name']}.{$condition}", $adminId, $links);
                $acceptedByItem[(int) $item->id] = ($acceptedByItem[(int) $item->id] ?? 0) + $quantity;
            }

            // What was taken for the job and not brought back was used: it is already out of stock, so this only says where it went.
            $takenByItem = [];
            foreach (DB::table('inventory_requests')->where('installation_id', $id)->whereNotNull('inventory_id')->get(['inventory_id', 'quantity']) as $request) {
                $takenByItem[(int) $request->inventory_id] = ($takenByItem[(int) $request->inventory_id] ?? 0) + (int) $request->quantity;
            }
            foreach ($takenByItem as $inventoryId => $takenQuantity) {
                $used = $takenQuantity - ($acceptedByItem[$inventoryId] ?? 0);
                $item = DB::table('inventory')->where('id', $inventoryId)->first();
                if ($used > 0 && $item) {
                    StockMovements::record($inventoryId, 0, (int) $item->quantity, 'job_used', "{$used} used to install {$data['full_name']} ({$employee}).", $adminId, $links);
                }
            }

            DB::table('client_installations')->where('id', $id)->update([
                'status' => 'confirmed', 'client_id' => $clientId, 'reviewed_by' => $adminId, 'reviewed_at' => now(),
                'client_name' => $data['full_name'], 'contact_number' => $data['contact_number'], 'email' => $data['email'],
                'barangay_name' => $data['barangay_name'], 'exact_address' => $data['exact_address'], 'internet_plan_id' => $data['plan']->id,
                'payment_mode' => $data['payment_mode'], 'ip_address' => $data['ip_address'], 'facebook_account' => $data['facebook_account'],
                'installation_date' => $data['installation_date'], 'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
                'nap_box_id' => (int) $port->nap_box_id, 'nap_port_id' => (int) $port->id,
                'admin_remarks' => trim((string) ($input['admin_remarks'] ?? '')) ?: null, 'updated_at' => now(),
            ]);

            return $clientId;
        });
    }

    /** Reject a report: nothing is added, the items it used are free for the employee to report again. */
    public static function reject(int $id, string $reason, int $adminId, string $branch): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Write why the report is rejected, so the employee knows what to fix.');
        }

        DB::transaction(function () use ($id, $reason, $adminId, $branch): void {
            self::lockSubmitted($id, $branch);
            DB::table('inventory_requests')->where('installation_id', $id)->update(['installation_id' => null, 'updated_at' => now()]);
            DB::table('client_installations')->where('id', $id)->update([
                'status' => 'rejected', 'admin_remarks' => $reason, 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** The report, locked, when it is still waiting and belongs to the branch. */
    private static function lockSubmitted(int $id, string $branch): object
    {
        $report = DB::table('client_installations')->where('id', $id)->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch])->lockForUpdate()->first();
        abort_if(! $report, 404);
        if ($report->status !== 'submitted') {
            throw new DomainException('This report was already ' . $report->status . '.');
        }

        return $report;
    }
}

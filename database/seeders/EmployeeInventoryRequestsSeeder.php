<?php

namespace Database\Seeders;

use App\Support\NapBoxes;
use App\Support\StockMovements;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Item-out and item-return requests of the technicians, the way the employee app files them and the branch admin decides them: some
 * waiting, most approved (the stock goes down, or back up for a return, with an entry in the stock history), some denied.
 *
 * - Of the active technicians of a branch (employees with an app account) about 45 in a hundred filed requests, three to six each over
 *   the last weeks; an employee that already has requests is left alone.
 * - A request is decided by the branch admin when the branch has one, otherwise by the super admin; the newest requests are the ones
 *   still waiting. When the shelf cannot cover an item out the admin orders more first (a "stock received" entry); a return only
 *   brings back what was taken out.
 * - Nothing here touches a NAP Box item: those are installed by the branch admin, never requested by an employee.
 *
 *   php artisan db:seed --class=EmployeeInventoryRequestsSeeder
 *
 * Run BranchStaffAttendanceSeeder and BranchStockNapSeeder first (the employees and the stock come from them).
 */
class EmployeeInventoryRequestsSeeder extends Seeder
{
    /** item => [least, most] per request */
    private const OUT_ITEMS = [
        'Fiber Cable' => [30, 150], 'Patch Cord' => [2, 6], 'Pigtail' => [2, 6], 'Fast Connector' => [4, 10], 'Splitter' => [1, 2],
        'ONU' => [1, 3], 'Router' => [1, 2], 'Cable Tie' => [20, 60], 'Cable Clamp' => [20, 60], 'LAN Connector' => [4, 10], 'Splice Sleeve' => [4, 10],
    ];

    private const AREAS = ['Installation', 'Installation', 'Installation', 'Repair', 'Repair', 'Maintenance', 'Other'];

    /** Newer than this many days and a request may still be waiting for the admin. */
    private const PENDING_DAYS = 2;

    /** Out of every hundred technicians, how many filed requests in the month. */
    private const FILING_PERCENT = 45;

    /** Newer than this many days and an item out may be for an installation that has not been reported yet. */
    private const INSTALLATION_DAYS = 7;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('The sample requests are only for a development or demo database.');
        }

        $superAdmin = (int) (DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id') ?? 0) ?: null;
        $admins = DB::table('users')->where('role', 'admin')->where('archived', 0)->orderBy('id')->pluck('id', 'branch_name')->all();
        $totals = ['pending' => 0, 'approved' => 0, 'denied' => 0];

        foreach (array_keys(config('bohol_branches', [])) as $branch) {
            $technicians = DB::table('employees')->where('branch_name', $branch)->where('status', 'active')->where('position', 'like', 'Technician%')
                ->whereNotNull('user_id')->orderBy('id')->get()
                ->reject(fn ($employee) => DB::table('inventory_requests')->where('employee_id', $employee->id)->exists())->values();
            if ($technicians->isEmpty()) {
                continue;
            }

            $stock = DB::table('inventory')->where('branch_name', $branch)->where('archived', 0)->where('status', 'active')
                ->whereIn('item_name', array_keys(self::OUT_ITEMS))->get()->keyBy('item_name');
            if ($stock->isEmpty()) {
                continue;
            }
            $barangays = array_values((array) (config('bohol_barangays')[$branch] ?? [$branch]));
            $decider = $admins[$branch] ?? $superAdmin;

            foreach ($this->plan($technicians, $stock->keys()->all(), $barangays) as $request) {
                $outcome = $this->file($request, $decider);
                $totals[$outcome]++;
            }
        }

        $this->command?->info("Requests: {$totals['approved']} approved, {$totals['pending']} waiting, {$totals['denied']} denied.");
    }

    /**
     * The moments the requests of one branch were filed, in order (the stock changes as they are decided, so they must be played in
     * order). What each one asks for is worked out when it is filed, from what the employee holds and the stock has at that moment.
     *
     * @return array<int, array{employee: object, key: string, when: Carbon, partners: \Illuminate\Support\Collection}>
     */
    private function plan($technicians, array $itemNames, array $barangays): array
    {
        $plan = [];
        $now = now();
        foreach ($technicians as $employee) {
            // Not every technician asks for items in a month.
            if (crc32("files|{$employee->employee_code}") % 100 >= self::FILING_PERCENT) {
                continue;
            }
            $count = 3 + crc32("count|{$employee->employee_code}") % 4;
            $moments = [];
            for ($n = 0; $n < $count; $n++) {
                $daysAgo = 1 + crc32("day|{$employee->employee_code}|{$n}") % 26;
                $minute = 8 * 60 + 30 + crc32("minute|{$employee->employee_code}|{$n}") % 480; // 08:30 to 16:30
                $moments[] = $now->copy()->subDays($daysAgo)->startOfDay()->addMinutes($minute);
            }
            sort($moments);
            foreach ($moments as $n => $when) {
                $plan[] = [
                    'employee' => $employee, 'key' => "{$employee->employee_code}|{$n}", 'when' => $when,
                    'partners' => $technicians->where('id', '!=', $employee->id)->values(),
                    'items' => $itemNames, 'barangays' => $barangays,
                ];
            }
        }
        usort($plan, fn (array $a, array $b) => $a['when'] <=> $b['when']);

        return $plan;
    }

    /**
     * What the employee holds: the approved items out minus the approved returns, per item name.
     *
     * @return array<string, int>
     */
    private function held(int $employeeId): array
    {
        $held = [];
        $rows = DB::table('inventory_requests')->where('employee_id', $employeeId)->where('status', 'approved')
            ->selectRaw('item_name, request_type, SUM(quantity) AS total')->groupBy('item_name', 'request_type')->get();
        foreach ($rows as $row) {
            $held[$row->item_name] = ($held[$row->item_name] ?? 0) + ($row->request_type === 'return' ? -1 : 1) * (int) $row->total;
        }

        return array_filter($held, fn (int $quantity) => $quantity > 0);
    }

    /** Files one request and plays its decision on the stock. Returns what became of it: pending, approved or denied. */
    private function file(array $request, ?int $decider): string
    {
        $employee = $request['employee'];
        $key = $request['key'];
        $when = $request['when'];

        return DB::transaction(function () use ($request, $decider, $employee, $key, $when): string {
            $held = $this->held((int) $employee->id);
            $heldNames = array_keys($held);
            sort($heldNames);
            $return = $heldNames !== [] && crc32("type|{$key}") % 100 < 30;

            if ($return) {
                $name = $heldNames[crc32("item|{$key}") % count($heldNames)];
                $quantity = min($held[$name], max(1, (int) ceil($held[$name] * (0.3 + (crc32("share|{$key}") % 6) / 10))));
                $roll = crc32("cond|{$key}") % 100;
                $condition = $roll < 70 ? 'Good' : ($roll < 85 ? 'Damaged' : 'Defective');
                $area = null;
                $comments = $condition === 'Good' ? 'Not used, brought back.' : "Came back {$condition}.";
            } else {
                $name = $request['items'][crc32("item|{$key}") % count($request['items'])];
                [$least, $most] = self::OUT_ITEMS[$name];
                $quantity = $least + crc32("qty|{$key}") % ($most - $least + 1);
                $condition = 'Good';
                $area = self::AREAS[crc32("area|{$key}") % count(self::AREAS)];
                // An approved item out for an installation waits for the technician's installation report, so only the recent ones are for
                // installations; the older ones went to repairs and maintenance (their reports would be history that has not been entered).
                if ($area === 'Installation' && $when->diffInDays(now()) > self::INSTALLATION_DAYS) {
                    $area = crc32("older|{$key}") % 2 === 0 ? 'Repair' : 'Maintenance';
                }
                $barangay = $request['barangays'][crc32("where|{$key}") % count($request['barangays'])];
                $comments = match ($area) {
                    'Installation' => "For a new installation in {$barangay}.",
                    'Repair' => "Repair of a broken line in {$barangay}.",
                    'Maintenance' => "Monthly maintenance of the NAP boxes in {$barangay}.",
                    default => null,
                };
            }

            $item = DB::table('inventory')->where('branch_name', $employee->branch_name)->where('item_name', $name)->where('archived', 0)
                ->where('status', 'active')->lockForUpdate()->first();
            if (! $item || NapBoxes::isStockItem($item)) {
                return 'denied';
            }

            $roll = crc32("decision|{$key}") % 100;
            $decision = $when->diffInDays(now()) <= self::PENDING_DAYS && $roll < 60 ? 'pending' : ($roll >= 88 && ! $return ? 'denied' : 'approved');

            if (! $return && $decision !== 'denied') {
                // Stock that other waiting requests already hold cannot be handed out again. When the shelf cannot cover the request the
                // admin orders more before saying yes: a "stock received" entry, then the request goes on.
                $reserved = (int) DB::table('inventory_requests')->where('inventory_id', $item->id)->where('status', 'pending')->where('request_type', 'out')->sum('quantity');
                $short = $quantity - ((int) $item->quantity - $reserved);
                if ($short > 0) {
                    $balance = (int) $item->quantity + $short + 3 * (int) $item->reorder_level;
                    $last = DB::table('inventory_stock_movements')->where('inventory_id', $item->id)->max('created_at');
                    $stamp = $when->copy()->addMinutes(5);
                    if ($last && $stamp->lte($last)) {
                        $stamp = Carbon::parse($last)->addMinute();
                    }
                    DB::table('inventory')->where('id', $item->id)->update(['quantity' => $balance]);
                    StockMovements::record((int) $item->id, $balance - (int) $item->quantity, $balance, 'stock_received', 'Restocked from the supplier.', $decider, [], $stamp->gt(now()) ? now() : $stamp);
                    $item->quantity = $balance;
                }
            }

            // Decided after it was filed, and after the last thing that happened to this stock, so the history reads in order.
            $decidedAt = $when->copy()->addMinutes(15 + crc32("after|{$key}") % 225);
            $last = DB::table('inventory_stock_movements')->where('inventory_id', $item->id)->max('created_at');
            if ($last && $decidedAt->lte($last)) {
                $decidedAt = Carbon::parse($last)->addMinutes(1 + crc32("later|{$key}") % 4);
            }
            if ($decidedAt->gt(now())) {
                $decidedAt = now()->copy();
            }
            $partners = $request['partners'];
            $inCharge = $partners->isNotEmpty() && crc32("charge|{$key}") % 100 < 35 ? $partners[crc32("partner|{$key}") % $partners->count()]->full_name : $employee->full_name;

            $id = (int) DB::table('inventory_requests')->insertGetId([
                'employee_id' => $employee->id, 'employee_name' => $employee->full_name, 'inventory_id' => $item->id, 'item_name' => $item->item_name,
                'quantity' => $quantity, 'comments' => $comments, 'status' => $decision,
                'approved_by' => $decision === 'pending' ? null : $decider, 'approved_at' => $decision === 'pending' ? null : $decidedAt,
                'created_at' => $when, 'updated_at' => $decision === 'pending' ? $when : $decidedAt,
                'request_type' => $return ? 'return' : 'out', 'item_condition' => $condition,
                'area_used' => $area, 'person_in_charge' => $inCharge,
            ]);

            if ($decision === 'approved') {
                $change = $return ? $quantity : -$quantity;
                $balance = (int) $item->quantity + $change;
                DB::table('inventory')->where('id', $item->id)->update(['quantity' => $balance]);
                $remarks = $return
                    ? "Returned by {$employee->full_name} through request #{$id}. Condition: {$condition}."
                    : "Released to {$employee->full_name} through request #{$id}.";
                StockMovements::record((int) $item->id, $change, $balance, $return ? 'stock_returned' : 'employee_release', $remarks, $decider, ['employee_id' => (int) $employee->id], $decidedAt);
            }

            return $decision;
        });
    }
}

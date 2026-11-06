<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\NapBoxes;
use App\Support\StockMovements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Handles the stock-affecting decision on employee inventory requests. */
class InventoryRequestController extends Controller
{
    public function decide(Request $request, int $inventoryRequest, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'deny'], true), 404);
        // Requests are decided by the branch admin of the technician's branch; the super admin only looks.
        abort_unless(auth()->user()?->isBranchAdmin(), 403);

        try {
            $message = DB::transaction(function () use ($inventoryRequest, $decision): string {
                $requestRecord = DB::table('inventory_requests')
                    ->where('id', $inventoryRequest)
                    ->lockForUpdate()
                    ->first();

                if (! $requestRecord || $requestRecord->status !== 'pending') {
                    throw new \DomainException('This request has already been processed or no longer exists.');
                }

                // A multi-item request is stored as several stock rows. Lock and decide all of them together.
                $hasGroups = Schema::hasColumn('inventory_requests', 'request_group_id');
                $groupId = $hasGroups ? ($requestRecord->request_group_id ?? null) : null;
                $records = $groupId
                    ? DB::table('inventory_requests')->where('request_group_id', $groupId)->orderBy('id')->lockForUpdate()->get()
                    : collect([$requestRecord]);

                if ($records->isEmpty() || $records->contains(fn ($row) => $row->status !== 'pending')) {
                    throw new \DomainException('This request set has already been processed or no longer exists.');
                }
                if ($records->contains(fn ($row) => (int) $row->employee_id !== (int) $requestRecord->employee_id)) {
                    throw new \DomainException('This request set is invalid. Ask your administrator for help.');
                }
                abort_unless(BranchScope::canAccessEmployee($requestRecord->employee_id), 404);

                $ids = $records->pluck('id')->all();
                $count = $records->count();
                $isReturn = ($requestRecord->request_type ?? 'out') === 'return';
                if ($records->contains(fn ($row) => (($row->request_type ?? 'out') === 'return') !== $isReturn)) {
                    throw new \DomainException('This request set contains different request types. No changes were made.');
                }

                if ($decision === 'deny') {
                    DB::table('inventory_requests')->whereIn('id', $ids)->update([
                        'status' => 'denied', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                    ]);

                    return $count > 1 ? 'Request denied. All selected items were denied.' : 'Request denied successfully.';
                }

                $byInventory = $records->groupBy('inventory_id');
                $stock = DB::table('inventory')->whereIn('id', $byInventory->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($byInventory as $inventoryId => $lines) {
                    $inventory = $stock->get($inventoryId);
                    if (! $inventory || (! $isReturn && ($inventory->status !== 'active' || (bool) $inventory->archived)) || ! BranchScope::canAccessBranch($inventory->branch_name)) {
                        throw new \DomainException('One of the requested inventory items is unavailable.');
                    }
                    if (! $isReturn && NapBoxes::isStockItem($inventory)) {
                        throw new \DomainException('NAP boxes must be installed by the admin and cannot be released through an employee request.');
                    }
                    $quantity = (int) $lines->sum('quantity');
                    if (! $isReturn && (int) $inventory->quantity < $quantity) {
                        throw new \DomainException("Insufficient stock for {$inventory->item_name}. Available: {$inventory->quantity}.");
                    }

                    $balance = (int) $inventory->quantity + ($isReturn ? $quantity : -$quantity);
                    DB::table('inventory')->where('id', $inventory->id)->update(['quantity' => $balance]);
                    $condition = $lines->pluck('item_condition')->filter()->unique()->implode(', ');
                    $requestLabel = $groupId ? 'group ' . $groupId : '#' . $requestRecord->id;
                    if ($isReturn) {
                        StockMovements::record(
                            $inventory->id, $quantity, $balance, 'stock_returned',
                            "Returned by {$requestRecord->employee_name} through request {$requestLabel}." . ($condition ? " Condition: {$condition}." : ''), auth()->id(),
                            ['employee_id' => (int) $requestRecord->employee_id],
                        );
                    } else {
                        StockMovements::record(
                            $inventory->id, -$quantity, $balance, 'employee_release',
                            "Released to {$requestRecord->employee_name} through request {$requestLabel}.", auth()->id(),
                            ['employee_id' => (int) $requestRecord->employee_id],
                        );
                    }
                }

                DB::table('inventory_requests')->whereIn('id', $ids)->update([
                    'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                ]);

                return $isReturn
                    ? ($count > 1 ? 'Return accepted. All items are back in stock.' : 'Return accepted. The items are back in stock.')
                    : ($count > 1 ? 'Request approved. All selected items have been released.' : 'Request approved successfully. Stock has been updated.');
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }
}

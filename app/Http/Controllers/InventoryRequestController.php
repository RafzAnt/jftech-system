<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\StockMovements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                abort_unless(BranchScope::canAccessEmployee($requestRecord->employee_id), 404);

                if ($decision === 'deny') {
                    DB::table('inventory_requests')->where('id', $requestRecord->id)->update([
                        'status' => 'denied', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                    ]);

                    return 'Request denied successfully.';
                }

                $isReturn = ($requestRecord->request_type ?? 'out') === 'return';
                $inventory = DB::table('inventory')
                    ->where('id', $requestRecord->inventory_id)
                    ->when(! $isReturn, fn ($query) => $query->where('status', 'active')->where('archived', 0))
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || ! BranchScope::canAccessBranch($inventory->branch_name)) {
                    throw new \DomainException('The requested inventory item is unavailable.');
                }

                // An item brought back goes onto the shelf again (the admin has checked it and its condition).
                if ($isReturn) {
                    $balance = $inventory->quantity + $requestRecord->quantity;
                    DB::table('inventory')->where('id', $inventory->id)->update(['quantity' => $balance]);
                    $condition = $requestRecord->item_condition ? " Condition: {$requestRecord->item_condition}." : '';
                    StockMovements::record(
                        $inventory->id, (int) $requestRecord->quantity, (int) $balance, 'stock_returned',
                        "Returned by {$requestRecord->employee_name} through request #{$requestRecord->id}.{$condition}", auth()->id(),
                        ['employee_id' => (int) $requestRecord->employee_id],
                    );
                    DB::table('inventory_requests')->where('id', $requestRecord->id)->update([
                        'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                    ]);

                    return 'Return accepted. The items are back in stock.';
                }
                if ($inventory->quantity < $requestRecord->quantity) {
                    throw new \DomainException("Insufficient stock for {$requestRecord->item_name}. Available: {$inventory->quantity}.");
                }

                $balance = $inventory->quantity - $requestRecord->quantity;
                DB::table('inventory')->where('id', $inventory->id)->update(['quantity' => $balance]);
                StockMovements::record(
                    $inventory->id, -(int) $requestRecord->quantity, (int) $balance, 'employee_release',
                    "Released to {$requestRecord->employee_name} through request #{$requestRecord->id}.", auth()->id(),
                    ['employee_id' => (int) $requestRecord->employee_id],
                );
                DB::table('inventory_requests')->where('id', $requestRecord->id)->update([
                    'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                ]);

                return 'Request approved successfully. Stock has been updated.';
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }
}

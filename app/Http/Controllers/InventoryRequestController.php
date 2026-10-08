<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Handles the stock-affecting decision on employee inventory requests. */
class InventoryRequestController extends Controller
{
    public function decide(Request $request, int $inventoryRequest, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'deny'], true), 404);

        try {
            $message = DB::transaction(function () use ($inventoryRequest, $decision): string {
                $requestRecord = DB::table('inventory_requests')
                    ->where('id', $inventoryRequest)
                    ->lockForUpdate()
                    ->first();

                if (! $requestRecord || $requestRecord->status !== 'pending') {
                    throw new \DomainException('This request has already been processed or no longer exists.');
                }

                if ($decision === 'deny') {
                    DB::table('inventory_requests')->where('id', $requestRecord->id)->update([
                        'status' => 'denied', 'approved_by' => auth()->id(), 'approved_at' => now(), 'updated_at' => now(),
                    ]);

                    return 'Request denied successfully.';
                }

                $inventory = DB::table('inventory')
                    ->where('id', $requestRecord->inventory_id)
                    ->where('status', 'active')
                    ->where('archived', 0)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw new \DomainException('The requested inventory item is unavailable.');
                }
                if ($inventory->quantity < $requestRecord->quantity) {
                    throw new \DomainException("Insufficient stock for {$requestRecord->item_name}. Available: {$inventory->quantity}.");
                }

                $balance = $inventory->quantity - $requestRecord->quantity;
                DB::table('inventory')->where('id', $inventory->id)->update(['quantity' => $balance]);
                DB::table('inventory_stock_movements')->insert([
                    'inventory_id' => $inventory->id,
                    'quantity_change' => -$requestRecord->quantity,
                    'balance_after' => $balance,
                    'movement_type' => 'employee_release',
                    'remarks' => "Released to {$requestRecord->employee_name} through request #{$requestRecord->id}.",
                    'performed_by' => auth()->id(),
                    'created_at' => now(),
                ]);
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

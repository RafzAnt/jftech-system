<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $showArchived = $request->query('view') === 'archived';
        $items = Inventory::query()
            ->select('inventory.*')
            ->selectRaw("COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending'), 0) AS reserved_quantity")
            ->selectRaw("GREATEST(inventory.quantity - COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending'), 0), 0) AS available_quantity")
            ->where('archived', $showArchived)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get()
            ->map(fn ($item) => $item->toArray())
            ->all();
        $categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
        $filterCategory = (string) $request->query('category', '');
        $filteredItems = $filterCategory ? array_values(array_filter($items, fn ($item) => $item['category'] === $filterCategory)) : $items;
        $tab = $request->query('tab') === 'movements' ? 'movements' : 'stock';
        $movements = DB::table('inventory_stock_movements as movement')
            ->join('inventory as item', 'movement.inventory_id', '=', 'item.id')
            ->leftJoin('users as user', 'movement.performed_by', '=', 'user.id')
            ->select('movement.*', 'item.item_name', 'item.category', 'user.full_name as performed_by_name')
            ->when($filterCategory, fn ($query) => $query->where('item.category', $filterCategory))
            ->latest('movement.created_at')
            ->limit(100)
            ->get();

        $submissionToken = (string) $request->session()->get('inventory_submission_token', '');
        if ($submissionToken === '') {
            $submissionToken = (string) Str::uuid();
            $request->session()->put('inventory_submission_token', $submissionToken);
        }

        $commonItemNames = ['Access Point', 'Cable Clamp', 'Cable Tie', 'Ethernet Cable', 'Fiber Cable', 'LAN Connector', 'ONU', 'Patch Cord', 'Pigtail', 'Router', 'Splitter', 'UPS'];
        $existingInventoryNames = Inventory::query()->orderBy('item_name')->pluck('item_name')->all();
        $inventoryNameSuggestions = array_values(array_unique(array_merge($commonItemNames, $existingInventoryNames)));

        return view('pages.inventory', ['themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1, 'inventoryCssVersion' => @filemtime(public_path('css/inventory.css')) ?: 1, 'showArchived' => $showArchived, 'success' => session('success', ''), 'error' => session('error', ''), 'inventoryItems' => $items, 'categories' => $categories, 'archivedCount' => Inventory::where('archived', true)->count(), 'filterCategory' => $filterCategory, 'filteredItems' => $filteredItems, 'totalItems' => count($filteredItems), 'totalValue' => array_sum(array_map(fn ($item) => $item['quantity'] * $item['unit_price'], $filteredItems)), 'lowStock' => count(array_filter($filteredItems, fn ($item) => $item['available_quantity'] <= $item['reorder_level'])), 'tab' => $tab, 'movements' => $movements, 'submissionToken' => $submissionToken, 'inventoryNameSuggestions' => $inventoryNameSuggestions, 'existingInventoryNames' => $existingInventoryNames]);
    }
    public function store(Request $request): RedirectResponse
    {
        if (! hash_equals((string) $request->session()->pull('inventory_submission_token', ''), (string) $request->input('submission_token', ''))) {
            return back()->with('error', 'This inventory form was already submitted or has expired. Please try again.');
        }

        $data = $request->validate(['item_id' => ['nullable', 'integer', 'exists:inventory,id'], 'item_name' => ['required', 'string', 'max:100'], 'category' => ['required', 'string', 'max:50'], 'quantity' => ['required', 'integer', 'min:0'], 'add_quantity' => ['nullable', 'boolean'], 'unit_price' => ['required', 'numeric', 'min:0'], 'description' => ['nullable', 'string'], 'supplier' => ['nullable', 'string', 'max:100'], 'reorder_level' => ['required', 'integer', 'min:0']]);
        $id = $data['item_id'] ?? null;
        $addQuantity = (bool) ($data['add_quantity'] ?? false);
        unset($data['item_id']);
        unset($data['add_quantity']);
        $data['item_name'] = trim($data['item_name']);
        $data['category'] = trim($data['category']);
        DB::transaction(function () use ($id, $data, $addQuantity) {
            $duplicate = Inventory::query()
                ->whereRaw('LOWER(TRIM(item_name)) = ?', [strtolower($data['item_name'])])
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages(['item_name' => 'This item already exists. Use the existing item and record a stock movement instead.']);
            }

            if (! $id) {
                $item = Inventory::create($data + ['status' => 'active', 'archived' => false]);
                $this->recordMovement($item, $item->quantity, 'opening_balance', 'Opening stock when item was added.');
                return;
            }
            $item = Inventory::lockForUpdate()->findOrFail($id);
            $quantityToAdd = $addQuantity ? (int) $data['quantity'] : 0;
            unset($data['quantity']);
            $item->update($data);
            if ($quantityToAdd > 0) {
                $item->update(['quantity' => (int) $item->quantity + $quantityToAdd]);
                $this->recordMovement($item, $quantityToAdd, 'stock_received', 'Stock added through Edit Details.');
            }
        });
        return back()->with('success', $id ? 'Item updated successfully!' : 'Item added successfully!');
    }
    public function archive(Request $request, Inventory $inventory): RedirectResponse { $inventory->update(['archived' => true]); return back()->with('success', 'Item archived successfully!'); }
    public function restore(Request $request, Inventory $inventory): RedirectResponse { $inventory->update(['archived' => false]); return back()->with('success', 'Item restored successfully!'); }
    public function destroy(Request $request, Inventory $inventory): RedirectResponse { abort_unless($inventory->archived, 422); $inventory->delete(); return back()->with('success', 'Item deleted permanently!'); }

    public function recordStockMovement(Request $request, Inventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'in:stock_received,stock_issued,stock_returned,damaged,stock_count'],
            'quantity' => ['required', 'integer', 'min:0'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($inventory, $data): void {
                $item = Inventory::lockForUpdate()->findOrFail($inventory->id);
                if ($item->archived || $item->status !== 'active') {
                    throw new \DomainException('Only active inventory items can have stock movements.');
                }

                $quantity = (int) $data['quantity'];
                $change = match ($data['movement_type']) {
                    'stock_received', 'stock_returned' => $quantity,
                    'stock_issued', 'damaged' => -$quantity,
                    'stock_count' => $quantity - (int) $item->quantity,
                };
                $newBalance = (int) $item->quantity + $change;
                if ($newBalance < 0) {
                    throw new \DomainException("Insufficient on-hand stock for {$item->item_name}.");
                }
                $reserved = (int) DB::table('inventory_requests')
                    ->where('inventory_id', $item->id)
                    ->where('status', 'pending')
                    ->sum('quantity');
                if ($newBalance < $reserved) {
                    throw new \DomainException("This movement would leave {$item->item_name} below its {$reserved} unit(s) reserved for pending employee requests.");
                }

                $item->update(['quantity' => $newBalance]);
                $this->recordMovement($item, $change, $data['movement_type'], trim($data['remarks']));
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Stock movement recorded successfully.');
    }

    private function recordMovement(Inventory $item, int $quantityChange, string $type, string $remarks): void
    {
        DB::table('inventory_stock_movements')->insert(['inventory_id' => $item->id, 'quantity_change' => $quantityChange, 'balance_after' => $item->quantity, 'movement_type' => $type, 'remarks' => $remarks, 'performed_by' => auth()->id(), 'created_at' => now()]);
    }
}

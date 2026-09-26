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
        $items = Inventory::query()->where('archived', $showArchived)->orderBy('category')->orderBy('item_name')->get()->map(fn ($item) => $item->toArray())->all();
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

        return view('pages.inventory', ['themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1, 'inventoryCssVersion' => @filemtime(public_path('css/inventory.css')) ?: 1, 'showArchived' => $showArchived, 'success' => session('success', ''), 'error' => session('error', ''), 'inventoryItems' => $items, 'categories' => $categories, 'archivedCount' => Inventory::where('archived', true)->count(), 'filterCategory' => $filterCategory, 'filteredItems' => $filteredItems, 'totalItems' => count($filteredItems), 'totalValue' => array_sum(array_map(fn ($item) => $item['quantity'] * $item['unit_price'], $filteredItems)), 'lowStock' => count(array_filter($filteredItems, fn ($item) => $item['quantity'] <= $item['reorder_level'])), 'tab' => $tab, 'movements' => $movements, 'submissionToken' => $submissionToken]);
    }
    public function store(Request $request): RedirectResponse
    {
        if (! hash_equals((string) $request->session()->pull('inventory_submission_token', ''), (string) $request->input('submission_token', ''))) {
            return back()->with('error', 'This inventory form was already submitted or has expired. Please try again.');
        }

        $data = $request->validate(['item_id' => ['nullable', 'integer', 'exists:inventory,id'], 'item_name' => ['required', 'string', 'max:100'], 'category' => ['required', 'string', 'max:50'], 'quantity' => ['required', 'integer', 'min:0'], 'unit_price' => ['required', 'numeric', 'min:0'], 'description' => ['nullable', 'string'], 'supplier' => ['nullable', 'string', 'max:100'], 'reorder_level' => ['required', 'integer', 'min:0']]);
        $id = $data['item_id'] ?? null;
        unset($data['item_id']);
        DB::transaction(function () use ($id, $data) {
            if (! $id) {
                $duplicate = Inventory::query()
                    ->where('archived', false)
                    ->whereRaw('LOWER(item_name) = ?', [strtolower($data['item_name'])])
                    ->whereRaw('LOWER(category) = ?', [strtolower($data['category'])])
                    ->exists();
                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['item_name' => 'This item already exists in the selected category. Edit the existing item to update its stock.']);
                }
                $item = Inventory::create($data + ['status' => 'active', 'archived' => false]);
                $this->recordMovement($item, $item->quantity, 'opening_balance', 'Opening stock when item was added.');
                return;
            }
            $item = Inventory::lockForUpdate()->findOrFail($id);
            $change = (int) $data['quantity'] - (int) $item->quantity;
            $item->update($data);
            if ($change !== 0) $this->recordMovement($item, $change, 'adjustment', 'Stock adjusted from inventory management.');
        });
        return back()->with('success', $id ? 'Item updated successfully!' : 'Item added successfully!');
    }
    public function archive(Request $request, Inventory $inventory): RedirectResponse { $inventory->update(['archived' => true]); return back()->with('success', 'Item archived successfully!'); }
    public function restore(Request $request, Inventory $inventory): RedirectResponse { $inventory->update(['archived' => false]); return back()->with('success', 'Item restored successfully!'); }
    public function destroy(Request $request, Inventory $inventory): RedirectResponse { abort_unless($inventory->archived, 422); $inventory->delete(); return back()->with('success', 'Item deleted permanently!'); }

    private function recordMovement(Inventory $item, int $quantityChange, string $type, string $remarks): void
    {
        DB::table('inventory_stock_movements')->insert(['inventory_id' => $item->id, 'quantity_change' => $quantityChange, 'balance_after' => $item->quantity, 'movement_type' => $type, 'remarks' => $remarks, 'performed_by' => auth()->id(), 'created_at' => now()]);
    }
}

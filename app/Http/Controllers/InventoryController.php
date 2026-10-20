<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Support\BranchScope;
use App\Support\StockMovements;
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
        // A branch admin sees the stock of their own branch; the super admin sees every branch or picks one.
        $branch = BranchScope::resolve($request->query('branch'));
        $items = $this->inBranch(Inventory::query(), $branch)
            ->select('inventory.*')
            ->selectRaw("COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending' AND requests.request_type = 'out'), 0) AS reserved_quantity")
            ->selectRaw("GREATEST(inventory.quantity - COALESCE((SELECT SUM(requests.quantity) FROM inventory_requests AS requests WHERE requests.inventory_id = inventory.id AND requests.status = 'pending' AND requests.request_type = 'out'), 0), 0) AS available_quantity")
            ->where('archived', (int) $showArchived)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get()
            ->map(fn ($item) => $item->toArray())
            ->all();
        $categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
        $filterCategory = (string) $request->query('category', '');
        $filteredItems = $filterCategory ? array_values(array_filter($items, fn ($item) => $item['category'] === $filterCategory)) : $items;
        $tab = $request->query('tab') === 'movements' ? 'movements' : 'stock';
        // Each transaction also says which employee handled it and, for a job, the client and NAP box it was for.
        $linked = StockMovements::linked();
        $movements = $this->inBranch(DB::table('inventory_stock_movements as movement')
            ->join('inventory as item', 'movement.inventory_id', '=', 'item.id')
            ->leftJoin('users as user', 'movement.performed_by', '=', 'user.id')
            ->when($linked, fn ($query) => $query
                ->leftJoin('employees as employee', 'movement.employee_id', '=', 'employee.id')
                ->leftJoin('clients as client', 'movement.client_id', '=', 'client.id')
                ->leftJoin('nap_boxes as nap', 'movement.nap_box_id', '=', 'nap.id')
                ->addSelect('employee.full_name as employee_name', 'client.full_name as client_name', 'client.client_code', 'nap.name as nap_box_name')), $branch, 'item')
            ->addSelect('movement.*', 'item.item_name', 'item.category', 'item.branch_name', 'user.full_name as performed_by_name')
            ->when($filterCategory, fn ($query) => $query->where('item.category', $filterCategory))
            ->latest('movement.created_at')
            ->limit(100)
            ->get();

        $submissionToken = (string) $request->session()->get('inventory_submission_token', '');
        if ($submissionToken === '') {
            $submissionToken = (string) Str::uuid();
            $request->session()->put('inventory_submission_token', $submissionToken);
        }

        $commonItemNames = ['Access Point', 'Cable Clamp', 'Cable Tie', 'Ethernet Cable', 'Fiber Cable', 'LAN Connector', 'NAP Box', 'ONU', 'Patch Cord', 'Pigtail', 'Router', 'Splitter', 'UPS'];
        $existingInventoryNames = $this->inBranch(Inventory::query(), $branch)->orderBy('item_name')->pluck('item_name')->all();
        $inventoryNameSuggestions = array_values(array_unique(array_merge($commonItemNames, $existingInventoryNames)));

        // NAP boxes are equipment too, so they are counted with the stock. A project that has not run the
        // field work migration yet simply has none.
        try {
            $napBoxes = \App\Support\NapBoxes::boxes($branch);
        } catch (\Throwable) {
            $napBoxes = [];
        }
        $napBoxCount = count($napBoxes);
        $napPortsAvailable = array_sum(array_column($napBoxes, 'available_ports'));

        // The NAP boxes still on the shelf (the "NAP Box" items of the stock), next to the ones already installed.
        $napInStock = array_sum(array_column(\App\Support\NapBoxes::stockItems($branch), 'quantity'));
        // The category list of the form always offers the NAP Box category, even before the first NAP Box item exists.
        $modalCategories = array_values(array_unique(array_merge($categories, [\App\Support\NapBoxes::STOCK_CATEGORY])));
        sort($modalCategories);

        return view('pages.inventory', ['napInStock' => $napInStock, 'modalCategories' => $modalCategories, 'napBoxCount' => $napBoxCount, 'napPortsAvailable' => $napPortsAvailable, 'currentPage' => 'inventory','themeCssVersion' => @filemtime(public_path('css/jftech-theme.css')) ?: 1, 'inventoryCssVersion' => @filemtime(public_path('css/inventory.css')) ?: 1, 'showArchived' => $showArchived, 'success' => session('success', ''), 'error' => session('error', ''), 'inventoryItems' => $items, 'categories' => $categories, 'archivedCount' => $this->inBranch(Inventory::query(), $branch)->where('archived', 1)->count(), 'branch' => $branch, 'inventoryBranches' => array_keys(config('bohol_branches', [])), 'canManageInventory' => (bool) auth()->user()?->isBranchAdmin(), 'filterCategory' => $filterCategory, 'filteredItems' => $filteredItems, 'totalItems' => count($filteredItems), 'totalValue' => array_sum(array_map(fn ($item) => $item['quantity'] * $item['unit_price'], $filteredItems)), 'lowStock' => count(array_filter($filteredItems, fn ($item) => $item['available_quantity'] <= $item['reorder_level'])), 'tab' => $tab, 'movements' => $movements, 'submissionToken' => $submissionToken, 'inventoryNameSuggestions' => $inventoryNameSuggestions, 'existingInventoryNames' => $existingInventoryNames]);
    }
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStockKeeper();
        if (! hash_equals((string) $request->session()->pull('inventory_submission_token', ''), (string) $request->input('submission_token', ''))) {
            return back()->with('error', 'This stock form was already submitted or has expired. Please try again.');
        }

        $data = $request->validate(['item_id' => ['nullable', 'integer', 'exists:inventory,id'], 'item_name' => ['required', 'string', 'max:100'], 'category' => ['required', 'string', 'max:50'], 'quantity' => ['required', 'integer', 'min:0'], 'add_quantity' => ['nullable', 'boolean'], 'unit_price' => ['required', 'numeric', 'min:0'], 'description' => ['nullable', 'string'], 'supplier' => ['nullable', 'string', 'max:100'], 'reorder_level' => ['required', 'integer', 'min:0']]);
        $id = $data['item_id'] ?? null;
        $addQuantity = (bool) ($data['add_quantity'] ?? false);
        unset($data['item_id']);
        unset($data['add_quantity']);
        $data['item_name'] = trim($data['item_name']);
        $data['category'] = trim($data['category']);
        $branch = BranchScope::current();
        if ($id) {
            abort_unless(BranchScope::canAccessInventory($id), 404);
        }
        DB::transaction(function () use ($id, $data, $addQuantity, $branch) {
            // The same item may be stocked by another branch; the name only has to be unique within this one.
            $duplicate = Inventory::query()
                ->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch])
                ->whereRaw('LOWER(TRIM(item_name)) = ?', [strtolower($data['item_name'])])
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages(['item_name' => 'This item is already listed. Choose it and update the stock instead.']);
            }

            if (! $id) {
                $item = Inventory::create($data + ['branch_name' => $branch, 'sku' => 'SKU-' . strtoupper(Str::random(8)), 'status' => 'active', 'archived' => 0]);
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
    public function archive(Request $request, Inventory $inventory): RedirectResponse { $this->authorizeItem($inventory); $inventory->update(['archived' => 1]); return back()->with('success', 'Item archived successfully!'); }
    public function restore(Request $request, Inventory $inventory): RedirectResponse { $this->authorizeItem($inventory); $inventory->update(['archived' => 0]); return back()->with('success', 'Item restored successfully!'); }
    public function destroy(Request $request, Inventory $inventory): RedirectResponse { $this->authorizeItem($inventory); abort_unless($inventory->archived, 422); $inventory->delete(); return back()->with('success', 'Item deleted permanently!'); }

    public function recordStockMovement(Request $request, Inventory $inventory): RedirectResponse
    {
        $this->authorizeItem($inventory);
        $data = $request->validate([
            'movement_type' => ['required', 'in:stock_received,stock_issued,stock_returned,damaged,stock_count'],
            'quantity' => ['required', 'integer', 'min:0'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($inventory, $data): void {
                $item = Inventory::lockForUpdate()->findOrFail($inventory->id);
                if ($item->archived || $item->status !== 'active') {
                    throw new \DomainException('Only active items can be changed.');
                }

                $quantity = (int) $data['quantity'];
                $change = match ($data['movement_type']) {
                    'stock_received', 'stock_returned' => $quantity,
                    'stock_issued', 'damaged' => -$quantity,
                    'stock_count' => $quantity - (int) $item->quantity,
                };
                $newBalance = (int) $item->quantity + $change;
                if ($newBalance < 0) {
                    throw new \DomainException("Not enough available stock for {$item->item_name}.");
                }
                $reserved = (int) DB::table('inventory_requests')
                    ->where('inventory_id', $item->id)
                    ->where('status', 'pending')
                    ->where('request_type', 'out')
                    ->sum('quantity');
                if ($newBalance < $reserved) {
                    throw new \DomainException("This stock change would leave {$item->item_name} below its {$reserved} unit(s) set aside for pending employee requests.");
                }

                $item->update(['quantity' => $newBalance]);
                $this->recordMovement($item, $change, $data['movement_type'], trim($data['remarks']));
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Stock updated successfully.');
    }

    /** Stock is kept by the branch admin of the branch that holds it; the super admin only looks. */
    private function authorizeStockKeeper(): void
    {
        abort_unless(auth()->user()?->isBranchAdmin(), 403);
    }

    private function authorizeItem(Inventory $inventory): void
    {
        $this->authorizeStockKeeper();
        abort_unless(BranchScope::canAccessInventory($inventory->id), 404);
    }

    /** Limit a query on the inventory table (or an alias of it) to one branch; null means every branch. */
    private function inBranch($query, ?string $branch, string $alias = 'inventory')
    {
        [$sql, $params] = BranchScope::inventory($branch, $alias);

        return $sql === '' ? $query : $query->whereRaw(substr($sql, strlen(' AND ')), $params);
    }

    private function recordMovement(Inventory $item, int $quantityChange, string $type, string $remarks): void
    {
        StockMovements::record($item->id, $quantityChange, (int) $item->quantity, $type, $remarks, auth()->id());
    }
}

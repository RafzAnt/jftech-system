<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Inventory Management Module
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$inventoryCssVersion = filemtime(public_path('css/inventory.css'));

$success = '';
$error = '';
$showArchived = ($request->query('view') ?? '') === 'archived';

function ensureInventoryTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id INT PRIMARY KEY AUTO_INCREMENT,
            item_name VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            quantity INT DEFAULT 0,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            description TEXT,
            supplier VARCHAR(100),
            reorder_level INT DEFAULT 5,
            status ENUM('active', 'inactive') DEFAULT 'active',
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Keep installations created before archiving was added compatible.
    $columnExists = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'archived'")->fetch();
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    $pdo->exec("
        INSERT IGNORE INTO inventory (item_name, category, quantity, unit_price, description, supplier, reorder_level, status) VALUES
        ('Router', 'Networking Equipment', 50, 500.00, 'Wireless router', 'Supplier A', 10, 'active'),
        ('UPS', 'Power Equipment', 30, 750.00, 'Uninterruptible Power Supply', 'Supplier B', 5, 'active'),
        ('Patch Cord', 'Cabling', 200, 50.00, 'Fiber optic patch cord', 'Supplier C', 20, 'active'),
        ('Cable Tie', 'Accessories', 1000, 2.00, 'Plastic cable tie', 'Supplier C', 100, 'active'),
        ('Pigtail', 'Cabling', 100, 25.00, 'Fiber pigtail connector', 'Supplier C', 20, 'active'),
        ('Cable Clamp', 'Accessories', 200, 10.00, 'Cable clamp holder', 'Supplier C', 30, 'active')
    ");
}

function loadInventoryData(PDO $pdo, bool $showArchived): array
{
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE archived = ? ORDER BY category, item_name");
    $stmt->execute([$showArchived ? 1 : 0]);
    $inventoryItems = $stmt->fetchAll();
    $categories = array_unique(array_filter(array_column($inventoryItems, 'category')));

    return [$inventoryItems, $categories];
}

try {
    ensureInventoryTable($pdo);
    [$inventoryItems, $categories] = loadInventoryData($pdo, $showArchived);
} catch (Exception $e) {
    $inventoryItems = [];
    $categories = [];
    $error = 'Error: ' . $e->getMessage();
}

// Add/Edit Inventory Item
if ($request->isMethod('post') && $request->has('save_item')) {
    try {
        $itemId = $request->input('item_id') ?? null;
        $itemName = trim($request->input('item_name'));
        $category = trim($request->input('category'));
        $quantity = (int)$request->input('quantity');
        $unitPrice = (float)$request->input('unit_price');
        $description = trim($request->input('description'));
        $supplier = trim($request->input('supplier'));
        $reorderLevel = (int)$request->input('reorder_level');

        if ($itemId) {
            $stmt = $pdo->prepare("UPDATE inventory SET item_name = ?, category = ?, quantity = ?, unit_price = ?, description = ?, supplier = ?, reorder_level = ? WHERE id = ?");
            $stmt->execute([$itemName, $category, $quantity, $unitPrice, $description, $supplier, $reorderLevel, $itemId]);
            $success = 'Item updated successfully!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, unit_price, description, supplier, reorder_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$itemName, $category, $quantity, $unitPrice, $description, $supplier, $reorderLevel]);
            $success = 'Item added successfully!';
        }

        [$inventoryItems, $categories] = loadInventoryData($pdo, $showArchived);
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Archive / restore inventory items. Permanent deletion is only available from Archived.
if ($request->isMethod('post') && $request->has('archive_item')) {
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET archived = 1 WHERE id = ?");
        $stmt->execute([$request->input('item_id')]);
        $success = 'Item archived successfully!';
        
        [$inventoryItems, $categories] = loadInventoryData($pdo, $showArchived);
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($request->isMethod('post') && $request->has('restore_item')) {
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET archived = 0 WHERE id = ?");
        $stmt->execute([$request->input('item_id')]);
        $success = 'Item restored successfully!';

        [$inventoryItems, $categories] = loadInventoryData($pdo, $showArchived);
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

if ($request->isMethod('post') && $request->has('purge_item')) {
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND archived = 1");
        $stmt->execute([$request->input('item_id')]);
        $success = 'Item deleted permanently!';

        [$inventoryItems, $categories] = loadInventoryData($pdo, $showArchived);
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory WHERE archived = 1")->fetchColumn();

// Filter by category
$filterCategory = $request->query('category') ?? '';
$filteredItems = $filterCategory ? array_filter($inventoryItems, function($item) use ($filterCategory) {
    return $item['category'] === $filterCategory;
}) : $inventoryItems;

// Calculate totals
$totalItems = count($filteredItems);
$totalValue = array_sum(array_map(function($item) {
    return $item['quantity'] * $item['unit_price'];
}, $filteredItems));
$lowStock = count(array_filter($filteredItems, function($item) {
    return $item['quantity'] <= $item['reorder_level'];
}));
    return get_defined_vars();
};

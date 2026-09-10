<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Printable Inventory Stock Report
 */

$selectedCategory = $request->query('category') ?? '';
$searchTerm = $request->query('search') ?? '';
$showArchived = $request->has('archived') && $request->query('archived') == 1;

$query = "SELECT * FROM inventory WHERE 1=1";
$params = [];

if (!$showArchived) {
    $query .= " AND archived = 0";
}

if ($selectedCategory) {
    $query .= " AND category = ?";
    $params[] = $selectedCategory;
}

if ($searchTerm) {
    $query .= " AND (item_name LIKE ? OR description LIKE ? OR supplier LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$query .= " ORDER BY category ASC, item_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventoryItems = $stmt->fetchAll();

$totalItems = count($inventoryItems);
$totalQuantity = array_sum(array_map(function($i) { return (int) $i['quantity']; }, $inventoryItems));
$totalValue = array_sum(array_map(function($i) { return (float) $i['quantity'] * (float) $i['unit_price']; }, $inventoryItems));
$lowStockItems = count(array_filter($inventoryItems, function($i) { return (int) $i['quantity'] <= (int) $i['reorder_level']; }));

$categories = array_unique(array_filter(array_column($inventoryItems, 'category')));
sort($categories);

$categoryLabel = '';
if ($selectedCategory) {
    $categoryLabel = $selectedCategory;
}
    return get_defined_vars();
};

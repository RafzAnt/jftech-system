<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Login History Module (I) - Audit Log
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$loginHistoryCssVersion = filemtime(public_path('css/login_history.css'));

$searchUser = $request->query('search_user') ?? '';

$query = "SELECT * FROM login_history WHERE TRUE";
$params = [];

if ($searchUser) {
    $query .= " AND (username LIKE ? OR full_name LIKE ?)";
    $params[] = "%$searchUser%";
    $params[] = "%$searchUser%";
}

$query .= " ORDER BY login_time DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loginHistory = $stmt->fetchAll();

// Stats
$stmt = $pdo->query("SELECT COUNT(*) as total FROM login_history");
$totalLogins = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM login_history WHERE status = 'success'");
$successfulLogins = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM login_history WHERE status = 'failed'");
$failedLogins = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM login_history WHERE login_time >= CURRENT_TIMESTAMP - INTERVAL '24 hours'");
$activeToday = $stmt->fetch()['total'];
    return get_defined_vars();
};

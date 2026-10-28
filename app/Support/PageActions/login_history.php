<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Login History Module (I) - Audit Log
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$loginHistoryCssVersion = filemtime(public_path('css/login_history.css'));

$searchUser = trim((string) $request->query('search', ''));

// A branch admin sees the sign-ins of the accounts in their own branch; the super admin sees all.
// A failed attempt with an unknown username belongs to no branch, so only the super admin's view lists it.
$scopeBranch = \App\Support\BranchScope::current();
[$historyScope, $historyScopeParams] = \App\Support\BranchScope::viaUser($scopeBranch, 'login_history.user_id');

$query = "SELECT * FROM login_history WHERE TRUE" . $historyScope;
$params = $historyScopeParams;

// The one search box: a username or name, or the branch of the account, words narrowing each other (see LocationFilter).
[$searchSql, $searchParams] = \App\Support\LocationFilter::wordConditions($searchUser, [
    'login_history.username', 'login_history.full_name', '(SELECT users.branch_name FROM users WHERE users.id = login_history.user_id)',
]);
$query .= $searchSql;
array_push($params, ...$searchParams);

$query .= " ORDER BY login_time DESC, id DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loginHistory = $stmt->fetchAll();

// Stats
$countHistory = static function (string $extra = '', string $select = 'COUNT(*)') use ($pdo, $historyScope, $historyScopeParams): int {
    $stmt = $pdo->prepare("SELECT {$select} as total FROM login_history WHERE TRUE" . $historyScope . $extra);
    $stmt->execute($historyScopeParams);

    return (int) $stmt->fetch()['total'];
};
$totalLogins = $countHistory();
$successfulLogins = $countHistory(" AND status = 'success'");
$failedLogins = $countHistory(" AND status = 'failed'");

// login_time is set by the database, so compare against the database clock.
$driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
$dayAgo = match ($driver) {
    'pgsql' => "CURRENT_TIMESTAMP - INTERVAL '24 hours'",
    'sqlite' => "datetime('now', '-1 day')",
    default => 'CURRENT_TIMESTAMP - INTERVAL 24 HOUR',
};
$activeToday = $countHistory(" AND login_time >= $dayAgo", 'COUNT(DISTINCT user_id)');
    return get_defined_vars();
};

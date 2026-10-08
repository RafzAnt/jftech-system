<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * Logout - Clear Session
 */

// Log logout time
if (auth()->id()) {
    $stmt = $pdo->prepare("UPDATE login_history SET logout_time = CURRENT_TIMESTAMP WHERE id = (SELECT id FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 1)");
    $stmt->execute([auth()->id()]);
}

// Clear session
session_unset();
session_destroy();

header('Location: ../index.php');
exit;
    return get_defined_vars();
};

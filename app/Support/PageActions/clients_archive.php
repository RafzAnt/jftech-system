<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

    $themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
    $clientsBrowseCssVersion = filemtime(public_path('css/clients_browse.css'));

    $success = '';
    $error = '';

    // Handle restore / permanent delete actions
    if ($request->isMethod('post') && $request->has('action') && $request->has('client_id')) {
        $action = $request->input('action');
        $cid = (int) $request->input('client_id');
        try {
            if ($action === 'restore') {
                $pdo->prepare("UPDATE clients SET archived = 0, status = 'active' WHERE id = ?")->execute([$cid]);
                $success = 'Client restored.';
            } elseif ($action === 'delete') {
                // permanent delete: remove payments, bills, then client
                \Illuminate\Support\Facades\DB::transaction(function () use ($pdo, $cid) {
                    $pdo->prepare("DELETE FROM payments WHERE client_id = ?")->execute([$cid]);
                    $pdo->prepare("DELETE FROM bills WHERE client_id = ?")->execute([$cid]);
                    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$cid]);
                });
                $success = 'Client permanently deleted.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    $stmt = $pdo->query("SELECT c.*, b.barangay_name FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id WHERE c.archived = 1 ORDER BY c.updated_at DESC");
    $archivedClients = $stmt->fetchAll();

    return get_defined_vars();
};

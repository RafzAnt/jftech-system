<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=jftech_db","root","", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $row = $pdo->query("SELECT b.id as bill_id, b.client_id, b.amount, c.client_code, c.full_name FROM bills b JOIN clients c ON b.client_id = c.id WHERE c.client_code LIKE 'TEST-%' AND b.status IN ('pending','overdue') ORDER BY b.due_date ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (! $row) {
        echo "No pending TEST bill found.\n";
        exit(1);
    }
    $billId = $row['bill_id'];
    $clientId = $row['client_id'];
    $amount = $row['amount'];
    echo "Found pending bill id={$billId} for client {$row['client_code']} ({$row['full_name']}) amount={$amount}\n";

    $stmt = $pdo->prepare("INSERT INTO payments (client_id, bill_id, amount, payment_date, payment_method, reference_number, notes, received_by, submission_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $paymentDate = date('Y-m-d');
    $stmt->execute([$clientId, $billId, $amount, $paymentDate, 'cash', 'AUTO', 'Auto test payment', 1, uniqid('pst_')]);
    echo "Inserted payment of {$amount} for bill {$billId}\n";

    $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?")->execute([$billId]);
    echo "Marked bill {$billId} as paid\n";

    // Bootstrap the Laravel app and call the BillingService to create the
    // next billing period immediately for this client.
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    $billing = $app->make(\App\Support\BillingService::class);
    $created = $billing->generateNextBillForClient($clientId);
    echo "generateNextBillForClient created {$created} bill(s)\n";

    $bills = $pdo->prepare("SELECT id, billing_period_start, billing_period_end, due_date, status FROM bills WHERE client_id = ? ORDER BY billing_period_start DESC LIMIT 5");
    $bills->execute([$clientId]);
    $rows = $bills->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRecent bills for client {$row['client_code']}:\n";
    foreach ($rows as $r) {
        echo "id={$r['id']} start={$r['billing_period_start']} end={$r['billing_period_end']} due={$r['due_date']} status={$r['status']}\n";
    }

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

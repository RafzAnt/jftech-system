<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=jftech_db","root","", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $plan = $pdo->query("SELECT id, plan_name, monthly_price, installation_fee FROM internet_plans WHERE status='active' AND archived=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (! $plan) {
        echo "ERROR: no active internet_plan found. Create one first.\n";
        exit(1);
    }
    $barangay = $pdo->query("SELECT id FROM barangays LIMIT 1")->fetchColumn();
    if (! $barangay) {
        echo "ERROR: no barangay found.\n";
        exit(1);
    }

    $clients = [
        ['name' => 'Juan Dela Cruz', 'days_ago' => 0],
        ['name' => 'Maria Santos', 'days_ago' => 40],
        ['name' => 'Jose Rizal', 'days_ago' => 10],
        ['name' => 'Ana Lopez', 'days_ago' => 35],
        ['name' => 'Mark Reyes', 'days_ago' => 60],
        ['name' => 'Liza Fernandez', 'days_ago' => 20],
    ];

    foreach ($clients as $c) {
        $installationDate = date('Y-m-d', strtotime('-' . (int)$c['days_ago'] . ' days'));
        $clientCode = 'TEST-' . date('Ymd') . '-' . strtoupper(substr(md5($c['name'] . rand()), 0, 6));
        $fullName = $c['name'];
        $contactNumber = '0919' . rand(1000000, 9999999);
        $email = strtolower(str_replace(' ', '.', $fullName)) . '.' . rand(10,99) . '@example.com';

        $stmt = $pdo->prepare("INSERT INTO clients (client_code, full_name, contact_number, email, barangay_id, exact_address, latitude, longitude, marker_color, wifi_plan, internet_plan_id, monthly_rate, installation_fee, installation_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $clientCode, $fullName, $contactNumber, $email, (int)$barangay, 'Test Address', 9.75, 123.98, '#00BFFF', $plan['plan_name'], (int)$plan['id'], $plan['monthly_price'], $plan['installation_fee'], $installationDate, $installationDate . ' 00:00:00', $installationDate . ' 00:00:00'
        ]);
        $clientId = $pdo->lastInsertId();
        echo "Inserted client id={$clientId} name={$fullName} installed={$installationDate}\n";

        // Insert an initial bill with billing_period_start = installation date
        $billNumber = 'BILL-' . date('Ymd') . '-' . strtoupper(substr(md5($clientId . rand()), 0, 6));
        $billingStart = $installationDate;
        $billingEnd = date('Y-m-d', strtotime($billingStart . ' +30 days'));
        $dueDate = date('Y-m-d', strtotime($billingStart . ' +30 days'));
        $stmt = $pdo->prepare("INSERT INTO bills (client_id, bill_number, billing_period_start, billing_period_end, amount, due_date, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
        $stmt->execute([$clientId, $billNumber, $billingStart, $billingEnd, $plan['monthly_price'], $dueDate, 'Initial test bill']);
        echo "  -> Inserted bill due={$dueDate}\n";
    }

    // Run billing generator to create missing recurring bills and mark overdue
    chdir(__DIR__ . '/..');
    $out = [];
    exec('php artisan billing:generate 2>&1', $out);
    echo "\n" . implode("\n", $out) . "\n";

    // Show summary of test clients and their next pending/overdue bill
    $summary = $pdo->query("SELECT c.id, c.client_code, c.full_name, c.installation_date, (SELECT due_date FROM bills WHERE client_id=c.id AND status IN ('pending','overdue') ORDER BY due_date ASC LIMIT 1) as next_due, (SELECT status FROM bills WHERE client_id=c.id AND status IN ('pending','overdue') ORDER BY due_date ASC LIMIT 1) as next_status FROM clients c WHERE c.client_code LIKE 'TEST-%' ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSummary:\n";
    foreach ($summary as $s) {
        echo "id={$s['id']} code={$s['client_code']} name={$s['full_name']} installed={$s['installation_date']} next_due={$s['next_due']} status={$s['next_status']}\n";
    }

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

<?php
require __DIR__ . '/../vendor/autoload.php';
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=jftech_db","root","", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $plan = $pdo->query("SELECT id, plan_name, monthly_price, installation_fee FROM internet_plans WHERE status='active' AND archived=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (! $plan) {
        echo "ERROR: no active internet_plan found.\n";
        exit(1);
    }
    $barangay = $pdo->query("SELECT id FROM barangays LIMIT 1")->fetchColumn();
    if (! $barangay) {
        echo "ERROR: no barangay found.\n";
        exit(1);
    }
    $clientCode = 'TEST-' . date('YmdHis');
    $fullName = 'Test Client ' . uniqid();
    $contactNumber = '0919' . rand(1000000,9999999);
    $email = 'test+' . uniqid() . '@example.com';
    $stmt = $pdo->prepare("INSERT INTO clients (client_code, full_name, contact_number, email, barangay_id, exact_address, latitude, longitude, marker_color, wifi_plan, internet_plan_id, monthly_rate, installation_fee, installation_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
    $stmt->execute([$clientCode, $fullName, $contactNumber, $email, (int)$barangay, 'Test Address', 9.75, 123.98, '#00BFFF', $plan['plan_name'], (int)$plan['id'], $plan['monthly_price'], $plan['installation_fee'], date('Y-m-d')]);
    $clientId = $pdo->lastInsertId();
    echo "Inserted client id={$clientId} code={$clientCode}\n";
    $billNumber = 'BILL-' . date('YmdHis') . '-' . strtoupper(uniqid());
    $billingStart = date('Y-m-d');
    $billingEnd = date('Y-m-d', strtotime($billingStart . ' +30 days'));
    $dueDate = date('Y-m-d', strtotime($billingStart . ' +30 days'));
    $stmt = $pdo->prepare("INSERT INTO bills (client_id, bill_number, billing_period_start, billing_period_end, amount, due_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
    $stmt->execute([$clientId, $billNumber, $billingStart, $billingEnd, $plan['monthly_price'], $dueDate]);
    echo "Inserted initial bill for client {$clientId} due={$dueDate}\n";
    chdir(__DIR__ . '/..');
    $output = [];
    exec('php artisan billing:generate 2>&1', $output);
    echo implode("\n", $output) . "\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

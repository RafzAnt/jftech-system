<?php
try{
    $pdo=new PDO("mysql:host=127.0.0.1;port=3306;dbname=jftech_db","root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $clientId = $argv[1] ?? null;
    if(! $clientId){ echo "Usage: php insert_bill_for_client.php <clientId>\n"; exit(1); }
    $billNumber = 'BILL-' . date('YmdHis') . '-' . strtoupper(uniqid());
    $billingStart = date('Y-m-d');
    $billingEnd = date('Y-m-d', strtotime($billingStart . ' +30 days'));
    $dueDate = date('Y-m-d', strtotime($billingStart . ' +30 days'));
    $stmt = $pdo->prepare("INSERT INTO bills (client_id,bill_number,billing_period_start,billing_period_end,amount,due_date,status,notes,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$clientId, $billNumber, $billingStart, $billingEnd, 500.00, $dueDate, 'pending', 'Initial generated for test']);
    echo "Inserted bill for client {$clientId} due={$dueDate}\n";
    chdir(__DIR__ . '/..');
    $output=[];
    exec('php artisan billing:generate 2>&1', $output);
    echo implode("\n", $output) . "\n";
}catch(Exception $e){ echo 'ERROR: ' . $e->getMessage() . "\n"; }

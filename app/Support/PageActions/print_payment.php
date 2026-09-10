<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

    $paymentId = (int) $request->query('id');
    abort_unless($paymentId > 0, 404);

    $payment = $pdo->prepare("
        SELECT p.*, c.full_name AS client_name, c.client_code, c.contact_number,
               b.bill_number, b.amount AS bill_amount,
               (COALESCE(b.amount, 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE bill_id = p.bill_id), 0)) AS balance,
               u.full_name AS received_by_name
        FROM payments p
        JOIN clients c ON p.client_id = c.id
        LEFT JOIN bills b ON p.bill_id = b.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.id = ?
    ");
    $payment->execute([$paymentId]);
    $payment = $payment->fetch();

    abort_unless($payment, 404);

    return compact('payment');
};
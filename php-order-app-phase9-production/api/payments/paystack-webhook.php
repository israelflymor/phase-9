<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) json_response(['error' => 'Invalid payload'], 422);

$ref = $data['data']['reference'] ?? '';
$status = $data['data']['status'] ?? '';
$amount = (($data['data']['amount'] ?? 0) / 100);
$tenantId = (int)($data['data']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['metadata']['order_id'] ?? 0);

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "paystack", ?, ?, ?)');
$stmt->execute([$tenantId ?: 1, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && $status === 'success') {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'paystack','ref'=>$ref]);
}
json_response(['received' => true]);

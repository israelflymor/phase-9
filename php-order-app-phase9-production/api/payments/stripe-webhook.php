<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) json_response(['error' => 'Invalid payload'], 422);

$ref = $data['data']['object']['payment_intent'] ?? '';
$status = $data['type'] ?? '';
$tenantId = (int)($data['data']['object']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['object']['metadata']['order_id'] ?? 0);
$amount = (($data['data']['object']['amount_total'] ?? 0) / 100);

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "stripe", ?, ?, ?)');
$stmt->execute([$tenantId ?: 1, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && strpos($status, 'checkout.session.completed') !== false) {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'stripe','ref'=>$ref]);
}
json_response(['received' => true]);

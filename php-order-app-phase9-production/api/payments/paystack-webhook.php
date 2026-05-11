<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';

enforce_rate_limit_or_die('paystack_webhook', 120, 60);
$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!PAYSTACK_WEBHOOK_SECRET) json_response(['error' => 'Webhook secret not configured'], 500);
$expected = hash_hmac('sha512', $raw, PAYSTACK_WEBHOOK_SECRET);
if (!$signature || !hash_equals(strtolower($expected), strtolower($signature))) {
    json_response(['error' => 'Signature verification failed'], 401);
}

$data = json_decode($raw, true);
if (!$data) json_response(['error' => 'Invalid payload'], 422);

$ref = $data['data']['reference'] ?? '';
$status = $data['data']['status'] ?? '';
$amount = (($data['data']['amount'] ?? 0) / 100);
$tenantId = (int)($data['data']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['metadata']['order_id'] ?? 0);
if ($tenantId < 1 || !$ref) json_response(['error' => 'Missing payment metadata'], 422);

$stmt = $pdo->prepare('SELECT id FROM payments WHERE gateway = "paystack" AND transaction_ref = ? LIMIT 1');
$stmt->execute([$ref]);
if ($stmt->fetchColumn()) json_response(['received' => true, 'deduplicated' => true]);

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "paystack", ?, ?, ?)');
$stmt->execute([$tenantId, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && $status === 'success') {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'paystack','ref'=>$ref]);
}
json_response(['received' => true]);

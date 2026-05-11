<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';

enforce_rate_limit_or_die('stripe_webhook', 120, 60);
$raw = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if (!STRIPE_WEBHOOK_SECRET) json_response(['error' => 'Webhook secret not configured'], 500);
if (!$sigHeader || !preg_match('/t=(\d+),v1=([a-f0-9]+)/i', $sigHeader, $m)) json_response(['error' => 'Invalid signature header'], 401);
$timestamp = $m[1];
$signature = strtolower($m[2]);
$expected = hash_hmac('sha256', $timestamp . '.' . $raw, STRIPE_WEBHOOK_SECRET);
if (!hash_equals($expected, $signature)) json_response(['error' => 'Signature verification failed'], 401);

$data = json_decode($raw, true);
if (!$data) json_response(['error' => 'Invalid payload'], 422);

$ref = $data['data']['object']['payment_intent'] ?? '';
$status = $data['type'] ?? '';
$tenantId = (int)($data['data']['object']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['object']['metadata']['order_id'] ?? 0);
$amount = (($data['data']['object']['amount_total'] ?? 0) / 100);
if ($tenantId < 1 || !$ref) json_response(['error' => 'Missing payment metadata'], 422);

$stmt = $pdo->prepare('SELECT id FROM payments WHERE gateway = "stripe" AND transaction_ref = ? LIMIT 1');
$stmt->execute([$ref]);
if ($stmt->fetchColumn()) json_response(['received' => true, 'deduplicated' => true]);

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "stripe", ?, ?, ?)');
$stmt->execute([$tenantId, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && strpos($status, 'checkout.session.completed') !== false) {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'stripe','ref'=>$ref]);
}
json_response(['received' => true]);

<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/audit.php';
$rawPayload = file_get_contents('php://input');
rate_limit_or_die($pdo, 'stripe_webhook_ip', get_client_ip(), 120, 60, true);
verify_stripe_signature_or_die($rawPayload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$data = json_decode($rawPayload, true);
if (!$data) api_error('Invalid payload', 422, 'invalid_payload');

$ref = $data['data']['object']['payment_intent'] ?? '';
$status = $data['type'] ?? '';
$tenantId = (int)($data['data']['object']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['object']['metadata']['order_id'] ?? 0);
$amount = (($data['data']['object']['amount_total'] ?? 0) / 100);
if (!$tenantId || !$ref) api_error('Invalid payload', 422, 'invalid_payload');

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "stripe", ?, ?, ?)');
$stmt->execute([$tenantId, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && strpos($status, 'checkout.session.completed') !== false) {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'stripe','ref'=>$ref]);
}
audit_log($pdo, $tenantId, null, 'payment.webhook_stripe_processed', ['order_id' => $orderId, 'ref' => $ref, 'status' => $status]);
api_success(['received' => true]);

<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/audit.php';
$rawPayload = file_get_contents('php://input');
rate_limit_or_die($pdo, 'paystack_webhook_ip', get_client_ip(), 120, 60, true);
verify_paystack_signature_or_die($rawPayload, $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
$data = json_decode($rawPayload, true);
if (!$data) api_error('Invalid payload', 422, 'invalid_payload');

$ref = $data['data']['reference'] ?? '';
$status = $data['data']['status'] ?? '';
$amount = (($data['data']['amount'] ?? 0) / 100);
$tenantId = (int)($data['data']['metadata']['tenant_id'] ?? 0);
$orderId = (int)($data['data']['metadata']['order_id'] ?? 0);
if (!$tenantId || !$ref) api_error('Invalid payload', 422, 'invalid_payload');

$stmt = $pdo->prepare('INSERT INTO payments (tenant_id, order_id, gateway, transaction_ref, amount, status) VALUES (?, ?, "paystack", ?, ?, ?)');
$stmt->execute([$tenantId, $orderId ?: null, $ref, $amount, $status]);

if ($orderId && $status === 'success') {
    $stmt = $pdo->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$orderId, $tenantId]);
    emit_event($pdo, $tenantId, 'payment.completed', ['order_id'=>$orderId,'gateway'=>'paystack','ref'=>$ref]);
}
audit_log($pdo, $tenantId, null, 'payment.webhook_paystack_processed', ['order_id' => $orderId, 'ref' => $ref, 'status' => $status]);
api_success(['received' => true]);

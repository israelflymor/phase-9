<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('Method not allowed', 405, 'method_not_allowed');

$data = json_decode(file_get_contents('php://input'), true);
$clientName = trim($data['client_name'] ?? '');
$clientEmail = trim($data['client_email'] ?? '');
$items = $data['items'] ?? [];
$paymentMethod = trim($data['payment_method'] ?? 'pay_on_delivery');

if (!in_array($paymentMethod, ['pay_on_delivery', 'stripe', 'paystack'], true)) $paymentMethod = 'pay_on_delivery';
if ($clientEmail && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
    api_error('Invalid payload', 422, 'invalid_payload', ['client_email' => 'invalid_email']);
}
if (!$clientName || !is_array($items) || !$items) api_error('Invalid payload', 422, 'invalid_payload');
if (!enforce_plan_limit($pdo, $tenantId, 'orders')) api_error('Plan order limit reached', 403, 'plan_limit_reached');

$stmt = $pdo->prepare('INSERT INTO orders (tenant_id, client_name, client_email, items, payment_method) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$tenantId, $clientName, $clientEmail ?: null, json_encode($items), $paymentMethod]);
$orderId = (int)$pdo->lastInsertId();

audit_log($pdo, $tenantId, null, 'order.created_api', ['order_id' => $orderId, 'payment_method' => $paymentMethod]);
emit_event($pdo, $tenantId, 'order.created', ['order_id' => $orderId, 'source' => 'api']);
api_success(['order_id' => $orderId], 201);

<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
enforce_rate_limit_or_die('api_orders', 60, 60);

$data = json_decode(file_get_contents('php://input'), true);
$clientName = trim($data['client_name'] ?? '');
$clientEmail = trim($data['client_email'] ?? '');
$items = $data['items'] ?? [];
$paymentMethod = trim($data['payment_method'] ?? 'pay_on_delivery');

if (!$clientName || !is_array($items) || !$items) json_response(['error' => 'Invalid payload'], 422);
if ($clientEmail && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) json_response(['error' => 'Invalid email'], 422);
$allowedPaymentMethods = ['pay_on_delivery','card','bank_transfer'];
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) json_response(['error' => 'Invalid payment method'], 422);
foreach ($items as $item) {
    if (!is_array($item) || empty($item['name']) || !isset($item['qty']) || (int)$item['qty'] < 1) {
        json_response(['error' => 'Invalid item payload'], 422);
    }
}
if (!enforce_plan_limit($pdo, $tenantId, 'orders')) json_response(['error' => 'Plan order limit reached'], 403);

$stmt = $pdo->prepare('INSERT INTO orders (tenant_id, client_name, client_email, items, payment_method) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$tenantId, $clientName, $clientEmail ?: null, json_encode($items), $paymentMethod]);
$orderId = (int)$pdo->lastInsertId();

emit_event($pdo, $tenantId, 'order.created', ['order_id' => $orderId, 'source' => 'api']);
json_response(['success' => true, 'order_id' => $orderId]);

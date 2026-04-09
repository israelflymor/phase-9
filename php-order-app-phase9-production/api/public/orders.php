<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$data = json_decode(file_get_contents('php://input'), true);
$clientName = trim($data['client_name'] ?? '');
$clientEmail = trim($data['client_email'] ?? '');
$items = $data['items'] ?? [];
$paymentMethod = trim($data['payment_method'] ?? 'pay_on_delivery');

if (!$clientName || !is_array($items) || !$items) json_response(['error' => 'Invalid payload'], 422);
if (!enforce_plan_limit($pdo, $tenantId, 'orders')) json_response(['error' => 'Plan order limit reached'], 403);

$stmt = $pdo->prepare('INSERT INTO orders (tenant_id, client_name, client_email, items, payment_method) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$tenantId, $clientName, $clientEmail ?: null, json_encode($items), $paymentMethod]);
$orderId = (int)$pdo->lastInsertId();

emit_event($pdo, $tenantId, 'order.created', ['order_id' => $orderId, 'source' => 'api']);
json_response(['success' => true, 'order_id' => $orderId]);

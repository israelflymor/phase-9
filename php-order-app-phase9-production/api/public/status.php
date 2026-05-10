<?php
require_once __DIR__ . '/_bootstrap.php';
$orderId = (int)getv('order_id');
if ($orderId <= 0) api_error('order_id is required', 422, 'invalid_payload');
$stmt = $pdo->prepare('SELECT id, payment_status, status, created_at FROM orders WHERE id = ? AND tenant_id = ? LIMIT 1');
$stmt->execute([$orderId, $tenantId]);
$order = $stmt->fetch();
if (!$order) api_error('Not found', 404, 'not_found');
api_success(['order' => $order]);

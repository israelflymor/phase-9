<?php
require_once __DIR__ . '/_bootstrap.php';
$orderId = (int)getv('order_id');
$stmt = $pdo->prepare('SELECT id, payment_status, status, created_at FROM orders WHERE id = ? AND tenant_id = ? LIMIT 1');
$stmt->execute([$orderId, $tenantId]);
$order = $stmt->fetch();
if (!$order) json_response(['error' => 'Not found'], 404);
json_response(['order' => $order]);

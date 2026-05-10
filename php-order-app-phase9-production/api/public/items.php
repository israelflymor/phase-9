<?php
require_once __DIR__ . '/_bootstrap.php';
$stmt = $pdo->prepare('SELECT id, name, price, sku, stock FROM items WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([$tenantId]);
api_success(['items' => $stmt->fetchAll()]);

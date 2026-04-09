<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!$apiKey) json_response(['error' => 'Missing API key'], 401);

$stmt = $pdo->prepare('
    SELECT k.*, t.subdomain, t.status
    FROM api_keys k
    JOIN tenants t ON t.id = k.tenant_id
    WHERE k.api_key = ? AND k.active = 1
    LIMIT 1
');
$stmt->execute([$apiKey]);
$key = $stmt->fetch();

if (!$key || $key['status'] !== 'active') json_response(['error' => 'Unauthorized'], 401);
$tenantId = (int)$key['tenant_id'];
?>

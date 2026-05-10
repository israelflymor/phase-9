<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/audit.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
rate_limit_or_die($pdo, 'public_api_ip', get_client_ip(), 120, 60, true);
if (!$apiKey) api_error('Missing API key', 401, 'missing_api_key');

$stmt = $pdo->prepare('
    SELECT k.*, t.subdomain, t.status
    FROM api_keys k
    JOIN tenants t ON t.id = k.tenant_id
    WHERE k.api_key = ? AND k.active = 1
    LIMIT 1
');
$stmt->execute([$apiKey]);
$key = $stmt->fetch();

if (!$key || $key['status'] !== 'active') api_error('Unauthorized', 401, 'unauthorized');
$tenantId = (int)$key['tenant_id'];
rate_limit_or_die($pdo, 'public_api_key', (string)$key['id'], 300, 60, true);
audit_log($pdo, $tenantId, null, 'api.request', ['path' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET']);
?>

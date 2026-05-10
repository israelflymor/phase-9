<?php
require_once __DIR__ . '/security.php';

function audit_log(PDO $pdo, $tenantId, $userId, $action, array $meta = []) {
    $stmt = $pdo->prepare('
        INSERT INTO audit_logs (tenant_id, user_id, action, meta_json, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $tenantId ?: null,
        $userId ?: null,
        $action,
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        get_client_ip()
    ]);
}
?>

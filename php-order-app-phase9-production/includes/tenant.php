<?php
require_once __DIR__ . '/helpers.php';

function resolve_tenant(PDO $pdo) {
    $store = getv('store');
    if (!$store && !empty($_SESSION['tenant_subdomain'])) {
        $store = $_SESSION['tenant_subdomain'];
    }
    if (!$store) return null;
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE subdomain = ? LIMIT 1');
    $stmt->execute([$store]);
    return $stmt->fetch() ?: null;
}

function require_tenant(PDO $pdo) {
    $tenant = resolve_tenant($pdo);
    if (!$tenant) exit('Tenant not found. Use ?store=demo.');
    if ($tenant['status'] !== 'active') exit('Tenant is not active.');
    return $tenant;
}

function tenant_plan(PDO $pdo, $tenantId) {
    $stmt = $pdo->prepare('
        SELECT p.*, s.status AS subscription_status, s.expires_at
        FROM subscriptions s
        JOIN plans p ON p.id = s.plan_id
        WHERE s.tenant_id = ?
        ORDER BY s.id DESC LIMIT 1
    ');
    $stmt->execute([$tenantId]);
    return $stmt->fetch();
}

function enforce_plan_limit(PDO $pdo, $tenantId, $resource) {
    $plan = tenant_plan($pdo, $tenantId);
    if (!$plan) return true;
    if ($resource === 'items') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        return (int)$stmt->fetchColumn() < (int)$plan['item_limit'];
    }
    if ($resource === 'orders') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        return (int)$stmt->fetchColumn() < (int)$plan['order_limit'];
    }
    return true;
}
?>

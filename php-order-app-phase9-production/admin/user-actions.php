<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/events.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);
verify_csrf_or_die();

if (($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('Only super_admin can manage users.');
}

$action = post('action');
$userId = (int)post('user_id');
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)$tenant['id'];

$stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
$stmt->execute([$userId, $tenantId]);
$target = $stmt->fetch();

if (!$target) {
    redirect_to('/admin/users.php?store=' . urlencode($tenant['subdomain']));
}

if ($userId === $currentUserId && in_array($action, ['disable'], true)) {
    redirect_to('/admin/users.php?store=' . urlencode($tenant['subdomain']) . '&error=self_lockout');
}

if ($target['role'] === 'super_admin' && $action === 'disable') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role = "super_admin" AND status = "active"');
    $stmt->execute([$tenantId]);
    $activeSuperAdmins = (int)$stmt->fetchColumn();
    if ($activeSuperAdmins <= 1) {
        redirect_to('/admin/users.php?store=' . urlencode($tenant['subdomain']) . '&error=last_super_admin');
    }
}

if ($action === 'disable') {
    $stmt = $pdo->prepare('UPDATE users SET status = "disabled" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$userId, $tenantId]);
    emit_event($pdo, $tenantId, 'user.disabled', ['user_id' => $userId]);
} elseif ($action === 'enable') {
    $stmt = $pdo->prepare('UPDATE users SET status = "active" WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$userId, $tenantId]);
    emit_event($pdo, $tenantId, 'user.enabled', ['user_id' => $userId]);
}

redirect_to('/admin/users.php?store=' . urlencode($tenant['subdomain']));

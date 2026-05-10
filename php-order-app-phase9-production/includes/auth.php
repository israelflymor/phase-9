<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
start_secure_session();

function require_login() {
    if (empty($_SESSION['user_id'])) redirect_to('/login.php');
}
function require_admin() {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
        http_response_code(403); exit('Access denied');
    }
}
function require_super_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        http_response_code(403); exit('Super admin only');
    }
}
function require_same_tenant_or_die($tenantId) {
    if ((int)($_SESSION['tenant_id'] ?? 0) !== (int)$tenantId) {
        http_response_code(403); exit('Tenant mismatch');
    }
}
?>

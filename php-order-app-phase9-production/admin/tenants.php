<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    rate_limit_or_die($pdo, 'super_admin_tenants', (string)($_SESSION['user_id'] ?? 0), 30, 60);
    $action = post('action');
    if ($action === 'create_tenant') {
        $name = substr(post('name'), 0, 255);
        $subdomain = strtolower(substr(post('subdomain'), 0, 255));
        $planCode = post('plan_code', 'free');
        $ownerEmail = filter_var(post('owner_email'), FILTER_VALIDATE_EMAIL) ? post('owner_email') : '';
        $ownerUsername = substr(post('owner_username'), 0, 100);
        $ownerPassword = post('owner_password');
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,62}$/', $subdomain)) {
            redirect_to('/admin/tenants.php');
        }
        if (!$ownerEmail || strlen($ownerPassword) < 8 || $name === '' || $ownerUsername === '') {
            redirect_to('/admin/tenants.php');
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO tenants (name, subdomain, plan_code, status) VALUES (?, ?, ?, "active")');
            $stmt->execute([$name, $subdomain, $planCode]);
            $tenantId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT id FROM plans WHERE code = ? LIMIT 1');
            $stmt->execute([$planCode]);
            $planId = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare('INSERT INTO subscriptions (tenant_id, plan_id, status) VALUES (?, ?, "active")');
            $stmt->execute([$tenantId, $planId]);

            $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, username, password, role) VALUES (?, ?, ?, ?, "super_admin")');
            $stmt->execute([$tenantId, $ownerEmail, $ownerUsername, password_hash($ownerPassword, PASSWORD_DEFAULT)]);

            $pdo->commit();
            audit_log($pdo, $tenantId, (int)($_SESSION['user_id'] ?? 0), 'tenant.created', ['subdomain' => $subdomain, 'plan' => $planCode]);
        } catch (Exception $e) {
            $pdo->rollBack();
        }
        redirect_to('/admin/tenants.php');
    } elseif ($action === 'toggle_status') {
        $tenantId = (int)post('tenant_id');
        $stmt = $pdo->prepare('UPDATE tenants SET status = IF(status="active","suspended","active") WHERE id = ?');
        $stmt->execute([$tenantId]);
        audit_log($pdo, $tenantId, (int)($_SESSION['user_id'] ?? 0), 'tenant.status_toggled', ['tenant_id' => $tenantId]);
        redirect_to('/admin/tenants.php');
    }
}
$tenants = $pdo->query('
    SELECT t.*,
           (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS users_count,
           (SELECT COUNT(*) FROM orders o WHERE o.tenant_id = t.id) AS orders_count
    FROM tenants t ORDER BY t.id DESC
')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Super Admin · Tenants</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>Super Admin · Tenants</h1><div class="nav"><a href="/logout.php">Logout</a></div></div>
<div class="card"><h2>Create tenant</h2><form method="post" class="grid"><?= csrf_field() ?><input type="hidden" name="action" value="create_tenant"><div><label class="small">Store name</label><input name="name" maxlength="255" required></div><div><label class="small">Subdomain / store code</label><input name="subdomain" pattern="[a-z0-9][a-z0-9\-]{1,62}" required></div><div><label class="small">Plan</label><select name="plan_code"><option value="free">free</option><option value="starter">starter</option><option value="growth">growth</option></select></div><div><label class="small">Owner email</label><input type="email" name="owner_email" required></div><div><label class="small">Owner username</label><input name="owner_username" maxlength="100" required></div><div><label class="small">Owner password</label><input type="password" name="owner_password" minlength="8" required></div><div><button type="submit">Create Tenant</button></div></form></div>
<div class="card"><h2>Tenant list</h2><table><thead><tr><th>ID</th><th>Name</th><th>Store</th><th>Plan</th><th>Status</th><th>Users</th><th>Orders</th><th>Action</th></tr></thead><tbody>
<?php foreach ($tenants as $tenant): ?><tr><td><?= (int)$tenant['id'] ?></td><td><?= h($tenant['name']) ?></td><td><?= h($tenant['subdomain']) ?></td><td><?= h($tenant['plan_code']) ?></td><td><?= h($tenant['status']) ?></td><td><?= (int)$tenant['users_count'] ?></td><td><?= (int)$tenant['orders_count'] ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="tenant_id" value="<?= (int)$tenant['id'] ?>"><button class="secondary" type="submit"><?= $tenant['status'] === 'active' ? 'Suspend' : 'Activate' ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></body></html>

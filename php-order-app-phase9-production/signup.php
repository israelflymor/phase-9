<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/events.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = post('name');
    $subdomain = strtolower(preg_replace('/[^a-z0-9-]/', '', post('subdomain')));
    $planCode = post('plan_code', 'free');
    $ownerEmail = post('owner_email');
    $ownerUsername = post('owner_username');
    $ownerPassword = post('owner_password');

    if (!$storeName || !$subdomain || !$ownerEmail || !$ownerUsername || !$ownerPassword) {
        $error = 'All fields are required.';
    } elseif (!in_array($planCode, ['free', 'starter', 'growth'], true)) {
        $error = 'Invalid plan selected.';
    } elseif (strlen($subdomain) < 3) {
        $error = 'Store code must be at least 3 characters.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM tenants WHERE subdomain = ? LIMIT 1');
            $stmt->execute([$subdomain]);
            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Store code already exists.');
            }

            $stmt = $pdo->prepare('INSERT INTO tenants (name, subdomain, plan_code, status, theme_json) VALUES (?, ?, ?, "active", ?)');
            $stmt->execute([$storeName, $subdomain, $planCode, json_encode(['color' => '#1d4ed8'])]);
            $tenantId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT id FROM plans WHERE code = ? LIMIT 1');
            $stmt->execute([$planCode]);
            $planId = (int)$stmt->fetchColumn();
            if ($planId <= 0) {
                throw new RuntimeException('Plan not found.');
            }

            $stmt = $pdo->prepare('INSERT INTO subscriptions (tenant_id, plan_id, status) VALUES (?, ?, "active")');
            $stmt->execute([$tenantId, $planId]);

            $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, username, password, role) VALUES (?, ?, ?, ?, "super_admin")');
            $stmt->execute([$tenantId, $ownerEmail, $ownerUsername, password_hash($ownerPassword, PASSWORD_DEFAULT)]);

            emit_event($pdo, $tenantId, 'tenant.onboarded', ['subdomain' => $subdomain, 'plan' => $planCode]);

            $pdo->commit();
            $success = 'Tenant created. You can now login to manage your store.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = $e->getMessage() ?: 'Unable to create tenant now.';
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tenant Onboarding</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container"><div class="card" style="max-width:640px;margin:40px auto">
<h1>Create your store</h1>
<p class="small">Launch your tenant in minutes and start accepting orders.</p>
<?php if ($success): ?><p class="badge ok"><?= h($success) ?></p><?php endif; ?>
<?php if ($error): ?><p class="badge danger"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="grid">
<div><label class="small">Store Name</label><input name="name" required></div>
<div><label class="small">Store Code (subdomain)</label><input name="subdomain" required placeholder="my-store"></div>
<div><label class="small">Plan</label><select name="plan_code"><option value="free">Free</option><option value="starter">Starter</option><option value="growth">Growth</option></select></div>
<div><label class="small">Owner Email</label><input type="email" name="owner_email" required></div>
<div><label class="small">Owner Username</label><input name="owner_username" required></div>
<div><label class="small">Owner Password</label><input type="password" name="owner_password" required></div>
<div class="row"><button type="submit">Create Tenant</button><a href="/login.php">Back to Login</a></div>
</form>
</div></div></body></html>

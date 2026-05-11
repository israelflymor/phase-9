<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/tenant.php';
session_start();

$error = '';
$tenant = resolve_tenant($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_rate_limit_or_die('login', 10, 300);
    verify_csrf_or_die();
    $store = normalize_store_code(post('store'));
    $email = strtolower(post('email'));
    $password = post('password');

    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE subdomain = ? LIMIT 1');
    $stmt->execute([$store]);
    $tenant = $stmt->fetch();

    if (!is_valid_store_code($store) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid login input.';
    } elseif (!$tenant || $tenant['status'] !== 'active') {
        $error = 'Invalid or inactive store.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE tenant_id = ? AND email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$tenant['id'], $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['tenant_id'] = (int)$tenant['id'];
            $_SESSION['tenant_subdomain'] = $tenant['subdomain'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            if ($user['role'] === 'client') {
                redirect_to('/tenant/storefront.php?store=' . urlencode($tenant['subdomain']));
            }
            redirect_to('/admin/index.php?store=' . urlencode($tenant['subdomain']));
        } else {
            $error = 'Login failed.';
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= h(APP_NAME) ?> - Login</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container"><div class="card" style="max-width:480px;margin:50px auto">
<h1><?= h(APP_NAME) ?></h1>
<p class="small">Demo tenant: <strong>demo</strong> · email <strong>owner@demo.local</strong> · password <strong>secret123</strong></p>
<?php if ($error): ?><p class="badge danger"><?= h($error) ?></p><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<label class="small">Store / Tenant</label><input name="store" value="<?= h($tenant['subdomain'] ?? getv('store', 'demo')) ?>" required>
<label class="small">Email</label><input type="email" name="email" required>
<label class="small">Password</label><input type="password" name="password" required>
<div class="row" style="margin-top:12px"><button type="submit">Login</button><a href="/tenant/onboard.php">Create your store</a><a href="/tenant/storefront.php?store=demo">Open Demo Storefront</a></div>
</form></div></div></body></html>

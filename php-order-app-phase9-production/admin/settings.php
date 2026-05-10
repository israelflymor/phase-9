<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/events.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'save') {
        $name = post('name');
        $logo = post('logo');
        $primaryColor = post('primary_color', '#1d4ed8');
        if (!$name) {
            $error = 'Store name is required.';
        } else {
            $theme = ['color' => $primaryColor];
            $stmt = $pdo->prepare('UPDATE tenants SET name = ?, logo = ?, theme_json = ? WHERE id = ?');
            $stmt->execute([$name, $logo ?: null, json_encode($theme), (int)$tenant['id']]);
            emit_event($pdo, (int)$tenant['id'], 'tenant.settings_updated', ['name' => $name]);
            $msg = 'Settings saved.';
            $tenant = require_tenant($pdo);
        }
    }
}

$theme = json_decode($tenant['theme_json'] ?? '', true) ?: [];
$plan = tenant_plan($pdo, (int)$tenant['id']);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tenant Settings</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>Settings · <?= h($tenant['name']) ?></h1><div class="nav"><a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a><a href="/logout.php">Logout</a></div></div>
<?php if ($msg): ?><div class="card"><span class="badge ok"><?= h($msg) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>
<div class="card">
<h2>Tenant profile</h2>
<form method="post" class="grid">
<input type="hidden" name="action" value="save">
<div><label class="small">Store Name</label><input name="name" value="<?= h($tenant['name']) ?>" required></div>
<div><label class="small">Store Code</label><input value="<?= h($tenant['subdomain']) ?>" disabled></div>
<div><label class="small">Logo URL</label><input name="logo" value="<?= h($tenant['logo'] ?? '') ?>" placeholder="https://..."></div>
<div><label class="small">Primary Color</label><input name="primary_color" value="<?= h($theme['color'] ?? '#1d4ed8') ?>" placeholder="#1d4ed8"></div>
<div><button type="submit">Save Settings</button></div>
</form>
</div>
<div class="card">
<h2>Subscription</h2>
<div class="small">Current plan: <strong><?= h($plan['code'] ?? $tenant['plan_code']) ?></strong></div>
<div class="small">Status: <strong><?= h($plan['subscription_status'] ?? 'active') ?></strong></div>
<div class="small">Order limit: <?= (int)($plan['order_limit'] ?? 0) ?> · Item limit: <?= (int)($plan['item_limit'] ?? 0) ?> · API limit: <?= (int)($plan['api_limit'] ?? 0) ?></div>
</div>
</div></body></html>

<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/events.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name = post('name');
    $logo = post('logo');
    $themeJsonRaw = post('theme_json');
    $themeJson = null;

    if (!$name) {
        $error = 'Store name is required.';
    } elseif ($themeJsonRaw !== '') {
        json_decode($themeJsonRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Theme JSON is invalid.';
        } else {
            $themeJson = $themeJsonRaw;
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare('UPDATE tenants SET name = ?, logo = ?, theme_json = ? WHERE id = ?');
        $stmt->execute([
            $name,
            $logo !== '' ? $logo : null,
            $themeJson,
            (int)$tenant['id']
        ]);

        emit_event($pdo, (int)$tenant['id'], 'tenant.settings_updated', [
            'actor_user_id' => (int)($_SESSION['user_id'] ?? 0),
            'name' => $name,
            'logo' => $logo !== ''
        ]);

        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$tenant['id']]);
        $tenant = $stmt->fetch() ?: $tenant;
        $msg = 'Settings saved.';
    }
}

$plan = tenant_plan($pdo, (int)$tenant['id']);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Settings · <?= h($tenant['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Tenant Settings · <?= h($tenant['name']) ?></h1>
        <div class="nav">
            <a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($msg): ?><div class="card"><span class="badge ok"><?= h($msg) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>

    <div class="card">
        <h2>Store identity</h2>
        <form method="post" class="grid">
            <?= csrf_field() ?>
            <div>
                <label class="small">Store name</label>
                <input name="name" required value="<?= h($tenant['name']) ?>">
            </div>
            <div>
                <label class="small">Logo URL / path</label>
                <input name="logo" placeholder="https://... or /uploads/logo.png" value="<?= h($tenant['logo'] ?? '') ?>">
            </div>
            <div style="grid-column:1/-1">
                <label class="small">Theme JSON</label>
                <textarea name="theme_json" rows="8" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px"><?= h($tenant['theme_json'] ?? '') ?></textarea>
            </div>
            <div><button type="submit">Save settings</button></div>
        </form>
    </div>

    <div class="card">
        <h2>Current plan</h2>
        <div class="small">Code: <strong><?= h($plan['code'] ?? $tenant['plan_code']) ?></strong></div>
        <div class="small">Name: <strong><?= h($plan['name'] ?? 'Unknown') ?></strong></div>
        <div class="small">Subscription status: <strong><?= h($plan['subscription_status'] ?? 'none') ?></strong></div>
        <div class="small">Expires: <strong><?= h($plan['expires_at'] ?? 'n/a') ?></strong></div>
    </div>
</div>
</body>
</html>

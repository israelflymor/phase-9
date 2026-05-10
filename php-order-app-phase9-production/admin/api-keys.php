<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    verify_csrf_or_die();
    $stmt = $pdo->prepare('INSERT INTO api_keys (tenant_id, label, api_key, permissions_json, active) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([(int)$tenant['id'], post('label') ?: 'Default', random_key(), json_encode(['orders:create'=>true,'items:read'=>true,'orders:status'=>true])]);
    redirect_to('/admin/api-keys.php?store=' . urlencode($tenant['subdomain']));
}
$stmt = $pdo->prepare('SELECT * FROM api_keys WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([(int)$tenant['id']]);
$keys = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>API Keys</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>API Keys · <?= h($tenant['name']) ?></h1><div class="nav"><a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a></div></div>
<div class="card"><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="create"><input name="label" placeholder="Key label"><button type="submit">Create API Key</button></form></div>
<div class="card"><table><thead><tr><th>Label</th><th>Key</th><th>Active</th><th>Created</th></tr></thead><tbody>
<?php foreach ($keys as $key): ?><tr><td><?= h($key['label']) ?></td><td><code><?= h($key['api_key']) ?></code></td><td><?= (int)$key['active'] ? 'Yes' : 'No' ?></td><td><?= h($key['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></body></html>

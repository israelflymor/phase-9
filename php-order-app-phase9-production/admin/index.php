<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    verify_csrf_or_die();
    rate_limit_or_die($pdo, 'admin_order_update', (string)($_SESSION['user_id'] ?? 0), 60, 60);
    $newStatus = post('new_status');
    $allowedStatuses = ['pending','packing','shipped','delivered','cancelled'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        http_response_code(422);
        exit('Invalid status');
    }
    $orderId = (int)post('order_id');
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$newStatus, $orderId, (int)$tenant['id']]);
    audit_log($pdo, (int)$tenant['id'], (int)($_SESSION['user_id'] ?? 0), 'order.status_changed', ['order_id' => $orderId, 'status' => $newStatus]);
    emit_event($pdo, (int)$tenant['id'], 'order.status_changed', ['order_id'=>$orderId,'status'=>$newStatus]);
    redirect_to('/admin/index.php?store=' . urlencode($tenant['subdomain']));
}
$plan = tenant_plan($pdo, (int)$tenant['id']);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 100');
$stmt->execute([(int)$tenant['id']]);
$orders = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tenant Admin</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><div><h1><?= h($tenant['name']) ?> Admin</h1><div class="small">User <?= h($_SESSION['username']) ?> · Role <?= h($_SESSION['role']) ?> · Plan <?= h($plan['code'] ?? $tenant['plan_code']) ?></div></div>
<div class="nav"><a href="/admin/items.php?store=<?= urlencode($tenant['subdomain']) ?>">Items</a><a href="/admin/api-keys.php?store=<?= urlencode($tenant['subdomain']) ?>">API Keys</a><?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?><a href="/admin/tenants.php">Super Admin</a><?php endif; ?><a href="/logout.php">Logout</a></div></div>
<div class="card"><h2>Orders</h2><table><thead><tr><th>#</th><th>Client</th><th>Items</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr>
<td><?= (int)$order['id'] ?></td>
<td><?= h($order['client_name']) ?><div class="small"><?= h($order['client_email']) ?></div></td>
<td><?php $list = json_decode($order['items'], true) ?: []; foreach ($list as $line): ?><div class="small"><?= h($line['name']) ?> × <?= (int)$line['qty'] ?></div><?php endforeach; ?></td>
<td><div><?= h($order['payment_method']) ?></div><div class="small"><?= h($order['payment_status']) ?></div></td>
<td><span class="badge"><?= h($order['status']) ?></span></td>
<td><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><select name="new_status" style="min-width:140px"><?php foreach (['pending','packing','shipped','delivered','cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select><button type="submit">Update</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div></div></body></html>

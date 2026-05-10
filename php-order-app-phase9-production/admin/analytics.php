<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);
$tenantId = (int)$tenant['id'];

$kpis = [
    'orders_total' => 0,
    'orders_paid' => 0,
    'orders_pending' => 0,
    'items_total' => 0,
    'users_total' => 0,
    'revenue_paid' => 0,
];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
$kpis['orders_total'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND payment_status = "paid"');
$stmt->execute([$tenantId]);
$kpis['orders_paid'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND status IN ("pending", "packing")');
$stmt->execute([$tenantId]);
$kpis['orders_pending'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
$kpis['items_total'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = "active"');
$stmt->execute([$tenantId]);
$kpis['users_total'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND status = "completed"');
$stmt->execute([$tenantId]);
$kpis['revenue_paid'] = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM orders WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d ASC');
$stmt->execute([$tenantId]);
$ordersByDay = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT event_name, COUNT(*) AS c FROM event_logs WHERE tenant_id = ? GROUP BY event_name ORDER BY c DESC LIMIT 6');
$stmt->execute([$tenantId]);
$topEvents = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Analytics</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>Analytics · <?= h($tenant['name']) ?></h1><div class="nav"><a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a><a href="/logout.php">Logout</a></div></div>
<div class="grid">
<div class="card"><div class="small">Total Orders</div><h2><?= (int)$kpis['orders_total'] ?></h2></div>
<div class="card"><div class="small">Paid Orders</div><h2><?= (int)$kpis['orders_paid'] ?></h2></div>
<div class="card"><div class="small">Pending Fulfillment</div><h2><?= (int)$kpis['orders_pending'] ?></h2></div>
<div class="card"><div class="small">Catalog Items</div><h2><?= (int)$kpis['items_total'] ?></h2></div>
<div class="card"><div class="small">Active Users</div><h2><?= (int)$kpis['users_total'] ?></h2></div>
<div class="card"><div class="small">Payment Revenue</div><h2>₦<?= h(number_format($kpis['revenue_paid'], 2)) ?></h2></div>
</div>
<div class="card"><h2>Orders trend (last 14 days)</h2>
<table><thead><tr><th>Date</th><th>Orders</th></tr></thead><tbody>
<?php foreach ($ordersByDay as $row): ?><tr><td><?= h($row['d']) ?></td><td><?= (int)$row['c'] ?></td></tr><?php endforeach; ?>
<?php if (!$ordersByDay): ?><tr><td colspan="2" class="small">No order activity in the last 14 days.</td></tr><?php endif; ?>
</tbody></table>
</div>
<div class="card"><h2>Top events</h2>
<table><thead><tr><th>Event</th><th>Count</th></tr></thead><tbody>
<?php foreach ($topEvents as $event): ?><tr><td><?= h($event['event_name']) ?></td><td><?= (int)$event['c'] ?></td></tr><?php endforeach; ?>
<?php if (!$topEvents): ?><tr><td colspan="2" class="small">No event logs yet.</td></tr><?php endif; ?>
</tbody></table>
</div>
</div></body></html>

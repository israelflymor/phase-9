<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';

require_admin();
$tenant = require_tenant($pdo);
require_same_tenant_or_die($tenant['id']);
$tenantId = (int)$tenant['id'];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
$totalOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND payment_status = "paid"');
$stmt->execute([$tenantId]);
$totalPaidOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND payment_status = "unpaid"');
$stmt->execute([$tenantId]);
$totalUnpaidOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND status = "delivered"');
$stmt->execute([$tenantId]);
$totalDeliveredOrders = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT id, items FROM orders WHERE tenant_id = ? AND payment_status = "paid" ORDER BY id DESC');
$stmt->execute([$tenantId]);
$paidOrders = $stmt->fetchAll();

$totalRevenue = 0;
foreach ($paidOrders as $order) {
    $lines = json_decode($order['items'], true) ?: [];
    foreach ($lines as $line) {
        $totalRevenue += ((int)($line['price'] ?? 0)) * ((int)($line['qty'] ?? 0));
    }
}

$stmt = $pdo->prepare('SELECT id, client_name, payment_status, status, created_at, items FROM orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 10');
$stmt->execute([$tenantId]);
$recentOrders = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE tenant_id = ?');
$stmt->execute([$tenantId]);
$itemCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT items FROM orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 500');
$stmt->execute([$tenantId]);
$orderItemsRows = $stmt->fetchAll();
$topItems = [];
foreach ($orderItemsRows as $row) {
    $lines = json_decode($row['items'], true) ?: [];
    foreach ($lines as $line) {
        $name = trim((string)($line['name'] ?? 'Unknown Item'));
        if (!isset($topItems[$name])) {
            $topItems[$name] = 0;
        }
        $topItems[$name] += (int)($line['qty'] ?? 0);
    }
}
arsort($topItems);
$topItems = array_slice($topItems, 0, 5, true);

$stmt = $pdo->prepare('SELECT event_name, payload_json, created_at FROM event_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 10');
$stmt->execute([$tenantId]);
$events = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT DATE(created_at) day_key, COUNT(*) total FROM orders WHERE tenant_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY day_key ASC');
$stmt->execute([$tenantId]);
$dailyRows = $stmt->fetchAll();
$dailyOrders = [];
foreach ($dailyRows as $row) {
    $dailyOrders[] = ['date' => $row['day_key'], 'orders' => (int)$row['total']];
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics · <?= h($tenant['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Tenant Analytics · <?= h($tenant['name']) ?></h1>
        <div class="nav">
            <a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <div class="grid">
        <div class="card"><h3>Total orders</h3><p><?= $totalOrders ?></p></div>
        <div class="card"><h3>Paid orders</h3><p><?= $totalPaidOrders ?></p></div>
        <div class="card"><h3>Unpaid orders</h3><p><?= $totalUnpaidOrders ?></p></div>
        <div class="card"><h3>Delivered orders</h3><p><?= $totalDeliveredOrders ?></p></div>
        <div class="card"><h3>Total revenue (paid)</h3><p>₦<?= h(number_format($totalRevenue)) ?></p><div class="small">Computed from paid order line items.</div></div>
        <div class="card"><h3>Item count</h3><p><?= $itemCount ?></p></div>
    </div>

    <div class="card">
        <h2>Top-selling items (last 500 orders)</h2>
        <?php if (!$topItems): ?>
            <p class="small">No order item data yet.</p>
        <?php else: ?>
            <table><thead><tr><th>Item</th><th>Units sold</th></tr></thead><tbody>
            <?php foreach ($topItems as $name => $qty): ?>
                <tr><td><?= h($name) ?></td><td><?= (int)$qty ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent orders</h2>
        <table><thead><tr><th>#</th><th>Client</th><th>Status</th><th>Payment</th><th>Created</th></tr></thead><tbody>
        <?php foreach ($recentOrders as $order): ?>
            <tr>
                <td><?= (int)$order['id'] ?></td>
                <td><?= h($order['client_name']) ?></td>
                <td><?= h($order['status']) ?></td>
                <td><?= h($order['payment_status']) ?></td>
                <td><?= h($order['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>

    <div class="card">
        <h2>Recent events</h2>
        <table><thead><tr><th>Event</th><th>Payload</th><th>When</th></tr></thead><tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= h($event['event_name']) ?></td>
                <td><code><?= h($event['payload_json']) ?></code></td>
                <td><?= h($event['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>

    <div class="card">
        <h2>Daily orders (last 7 days)</h2>
        <?php if (!$dailyOrders): ?>
            <p class="small">No recent order volume.</p>
        <?php else: ?>
            <table><thead><tr><th>Date</th><th>Orders</th></tr></thead><tbody>
            <?php foreach ($dailyOrders as $row): ?>
                <tr><td><?= h($row['date']) ?></td><td><?= (int)$row['orders'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

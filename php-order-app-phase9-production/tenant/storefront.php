<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/events.php';
session_start();

$tenant = require_tenant($pdo);
$tenantId = (int)$tenant['id'];
$msg = ''; $error = '';

$stmt = $pdo->prepare('SELECT * FROM items WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([$tenantId]);
$items = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!enforce_plan_limit($pdo, $tenantId, 'orders')) {
        $error = 'Plan order limit reached.';
    } else {
        $clientName = post('client_name');
        $clientEmail = post('client_email');
        $paymentMethod = post('payment_method', 'pay_on_delivery');
        $orderItems = [];
        foreach ($items as $item) {
            $qty = (int)($_POST['qty_' . $item['id']] ?? 0);
            if ($qty > 0) {
                $orderItems[] = ['id'=>(int)$item['id'],'name'=>$item['name'],'price'=>(int)$item['price'],'qty'=>$qty];
            }
        }
        if (!$clientName || !$orderItems) {
            $error = 'Enter your name and choose at least one item.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO orders (tenant_id, client_name, client_email, items, payment_method) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$tenantId, $clientName, $clientEmail ?: null, json_encode($orderItems), $paymentMethod]);
            $orderId = (int)$pdo->lastInsertId();
            emit_event($pdo, $tenantId, 'order.created', ['order_id'=>$orderId,'client_name'=>$clientName]);
            $msg = 'Order created successfully. Order #' . $orderId;
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= h($tenant['name']) ?> Storefront</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><div><h1><?= h($tenant['name']) ?></h1><div class="small">Store: <?= h($tenant['subdomain']) ?> · Plan: <?= h($tenant['plan_code']) ?></div></div><div class="nav"><a href="/login.php?store=<?= urlencode($tenant['subdomain']) ?>">Admin login</a></div></div>
<?php if ($msg): ?><div class="card"><span class="badge ok"><?= h($msg) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>
<div class="card"><h2>Place order</h2><form method="post"><?= csrf_input() ?>
<div class="grid"><div><label class="small">Your Name</label><input name="client_name" required></div><div><label class="small">Your Email</label><input type="email" name="client_email"></div></div>
<div class="row" style="margin-top:12px;margin-bottom:12px">
<label><input type="radio" name="payment_method" value="pay_on_delivery" checked> Pay on Delivery</label>
<label><input type="radio" name="payment_method" value="stripe"> Stripe (stub)</label>
<label><input type="radio" name="payment_method" value="paystack"> Paystack (stub)</label>
</div>
<div class="grid">
<?php foreach ($items as $item): ?>
<div class="card"><h3><?= h($item['name']) ?></h3><div class="small">₦<?= h(number_format($item['price'])) ?> · Stock <?= h($item['stock']) ?></div>
<label class="small">Quantity</label><input type="number" min="0" name="qty_<?= (int)$item['id'] ?>" value="0"></div>
<?php endforeach; ?>
</div>
<button type="submit">Submit Order</button></form></div></div></body></html>

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!enforce_plan_limit($pdo, (int)$tenant['id'], 'items') && post('action') === 'create') {
        $error = 'Item plan limit reached.';
    } else {
        if (post('action') === 'create') {
            $stmt = $pdo->prepare('INSERT INTO items (tenant_id, name, price, sku, stock) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(int)$tenant['id'], post('name'), (int)post('price'), post('sku'), (int)post('stock')]);
            emit_event($pdo, (int)$tenant['id'], 'item.created', ['name'=>post('name')]);
        } elseif (post('action') === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM items WHERE id = ? AND tenant_id = ?');
            $stmt->execute([(int)post('id'), (int)$tenant['id']]);
            emit_event($pdo, (int)$tenant['id'], 'item.deleted', ['id'=>(int)post('id')]);
        }
        redirect_to('/admin/items.php?store=' . urlencode($tenant['subdomain']));
    }
}
$stmt = $pdo->prepare('SELECT * FROM items WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([(int)$tenant['id']]);
$items = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Items</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>Items · <?= h($tenant['name']) ?></h1><div class="nav"><a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a><a href="/logout.php">Logout</a></div></div>
<?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>
<div class="card"><h2>Create item</h2><form method="post" class="grid"><input type="hidden" name="action" value="create"><div><label class="small">Name</label><input name="name" required></div><div><label class="small">Price</label><input type="number" name="price" required></div><div><label class="small">SKU</label><input name="sku"></div><div><label class="small">Stock</label><input type="number" name="stock" value="0"></div><div><button type="submit">Create Item</button></div></form></div>
<div class="card"><h2>Current items</h2><table><thead><tr><th>Name</th><th>Price</th><th>SKU</th><th>Stock</th><th></th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= h($item['name']) ?></td><td><?= (int)$item['price'] ?></td><td><?= h($item['sku']) ?></td><td><?= (int)$item['stock'] ?></td><td><form method="post" onsubmit="return confirm('Delete item?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></body></html>

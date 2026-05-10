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
    $action = post('action');
    if ($action === 'create') {
        $email = post('email');
        $username = post('username');
        $password = post('password');
        $role = post('role', 'admin');

        if (!$email || !$username || !$password) {
            $error = 'Email, username, and password are required.';
        } elseif (!in_array($role, ['admin', 'client'], true)) {
            $error = 'Invalid role selected.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, "active")');
                $stmt->execute([(int)$tenant['id'], $email, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                emit_event($pdo, (int)$tenant['id'], 'user.created', ['email' => $email, 'role' => $role]);
                $msg = 'User created.';
            } catch (Throwable $e) {
                $error = 'Unable to create user. Username or email may already exist.';
            }
        }
    }

    if ($action === 'toggle_status') {
        $userId = (int)post('user_id');
        if ($userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot disable yourself.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET status = IF(status="active","disabled","active") WHERE id = ? AND tenant_id = ? AND role != "super_admin"');
            $stmt->execute([$userId, (int)$tenant['id']]);
            emit_event($pdo, (int)$tenant['id'], 'user.status_toggled', ['user_id' => $userId]);
            $msg = 'User status updated.';
        }
    }
}

$stmt = $pdo->prepare('SELECT id, email, username, role, status, created_at FROM users WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([(int)$tenant['id']]);
$users = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tenant Users</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><div class="container">
<div class="topbar"><h1>Users · <?= h($tenant['name']) ?></h1><div class="nav"><a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a><a href="/logout.php">Logout</a></div></div>
<?php if ($msg): ?><div class="card"><span class="badge ok"><?= h($msg) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>
<div class="card">
<h2>Add team member</h2>
<form method="post" class="grid">
<input type="hidden" name="action" value="create">
<div><label class="small">Email</label><input type="email" name="email" required></div>
<div><label class="small">Username</label><input name="username" required></div>
<div><label class="small">Password</label><input type="password" name="password" required></div>
<div><label class="small">Role</label><select name="role"><option value="admin">Admin</option><option value="client">Client</option></select></div>
<div><button type="submit">Create User</button></div>
</form>
</div>
<div class="card"><h2>Current users</h2>
<table><thead><tr><th>ID</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr>
<td><?= (int)$user['id'] ?></td>
<td><?= h($user['email']) ?></td>
<td><?= h($user['username']) ?></td>
<td><?= h($user['role']) ?></td>
<td><?= h($user['status']) ?></td>
<td><?= h($user['created_at']) ?></td>
<td><?php if ($user['role'] === 'super_admin'): ?><span class="small">Owner</span><?php else: ?><form method="post"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button class="secondary" type="submit"><?= $user['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</div></body></html>

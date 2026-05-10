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
$errorCode = getv('error');
if ($errorCode === 'self_lockout') { $error = 'You cannot disable your own account while logged in.'; }
if ($errorCode === 'last_super_admin') { $error = 'Cannot disable the last active super_admin in this tenant.'; }
$canManage = (($_SESSION['role'] ?? '') === 'super_admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    verify_csrf_or_die();

    if (!$canManage) {
        $error = 'Only tenant super_admin can create users.';
    } else {
        $email = strtolower(post('email'));
        $username = post('username');
        $password = post('password');
        $role = post('role', 'client');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email is invalid.';
        } elseif (!$username || strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!in_array($role, ['client', 'admin'], true)) {
            $error = 'Role is invalid.';
        }

        if (!$error) {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, "active")');
                $stmt->execute([
                    (int)$tenant['id'],
                    $email,
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role
                ]);
                emit_event($pdo, (int)$tenant['id'], 'user.created', ['email' => $email, 'role' => $role]);
                $msg = 'User created.';
            } catch (Exception $e) {
                $error = 'User could not be created. Email or username may already exist.';
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT id, email, username, role, status, created_at FROM users WHERE tenant_id = ? ORDER BY id DESC');
$stmt->execute([(int)$tenant['id']]);
$users = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Users · <?= h($tenant['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1>Tenant Users · <?= h($tenant['name']) ?></h1>
        <div class="nav">
            <a href="/admin/index.php?store=<?= urlencode($tenant['subdomain']) ?>">Dashboard</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($msg): ?><div class="card"><span class="badge ok"><?= h($msg) ?></span></div><?php endif; ?>
    <?php if ($error): ?><div class="card"><span class="badge danger"><?= h($error) ?></span></div><?php endif; ?>

    <div class="card">
        <h2>Create user</h2>
        <?php if (!$canManage): ?>
            <p class="small">Only super_admin can create or manage tenant users.</p>
        <?php else: ?>
            <form method="post" class="grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div><label class="small">Email</label><input type="email" name="email" required></div>
                <div><label class="small">Username</label><input name="username" required></div>
                <div><label class="small">Password</label><input type="password" name="password" required></div>
                <div><label class="small">Role</label><select name="role"><option value="client">client</option><option value="admin">admin</option></select></div>
                <div><button type="submit">Create user</button></div>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Current tenant users</h2>
        <table>
            <thead><tr><th>ID</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int)$user['id'] ?></td>
                    <td><?= h($user['email']) ?></td>
                    <td><?= h($user['username']) ?></td>
                    <td><?= h($user['role']) ?></td>
                    <td><?= h($user['status']) ?></td>
                    <td><?= h($user['created_at']) ?></td>
                    <td>
                        <?php if ($canManage): ?>
                            <form method="post" action="/admin/user-actions.php?store=<?= urlencode($tenant['subdomain']) ?>" class="row">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <?php if ($user['status'] === 'active'): ?>
                                    <input type="hidden" name="action" value="disable">
                                    <button class="secondary" type="submit">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable">
                                    <button type="submit">Enable</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span class="small">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

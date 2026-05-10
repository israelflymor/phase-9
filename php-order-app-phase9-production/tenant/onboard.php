<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/events.php';
session_start();

$error = '';
$success = '';
$allowedPlans = ['free', 'starter'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name = post('name');
    $subdomain = normalize_store_code(post('subdomain'));
    $ownerEmail = strtolower(post('owner_email'));
    $ownerUsername = post('owner_username');
    $ownerPassword = post('owner_password');
    $planCode = post('plan_code', 'free');

    if (!$name || !$subdomain || !$ownerEmail || !$ownerUsername || !$ownerPassword) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-z0-9\-]{3,40}$/', $subdomain)) {
        $error = 'Store code must be 3-40 characters using lowercase letters, numbers, and dashes.';
    } elseif (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Owner email is not valid.';
    } elseif (strlen($ownerPassword) < 8) {
        $error = 'Owner password must be at least 8 characters.';
    } elseif (!in_array($planCode, $allowedPlans, true)) {
        $error = 'Invalid plan selected.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE subdomain = ? LIMIT 1');
        $stmt->execute([$subdomain]);
        if ($stmt->fetchColumn()) {
            $error = 'Store code is already in use.';
        }
    }

    if (!$error) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO tenants (name, subdomain, plan_code, status) VALUES (?, ?, ?, "active")');
            $stmt->execute([$name, $subdomain, $planCode]);
            $tenantId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT id FROM plans WHERE code = ? LIMIT 1');
            $stmt->execute([$planCode]);
            $planId = (int)$stmt->fetchColumn();
            if ($planId < 1) {
                throw new RuntimeException('Plan not found');
            }

            $stmt = $pdo->prepare('INSERT INTO subscriptions (tenant_id, plan_id, status) VALUES (?, ?, "active")');
            $stmt->execute([$tenantId, $planId]);

            $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, username, password, role) VALUES (?, ?, ?, ?, "super_admin")');
            $stmt->execute([
                $tenantId,
                $ownerEmail,
                $ownerUsername,
                password_hash($ownerPassword, PASSWORD_DEFAULT)
            ]);

            emit_event($pdo, $tenantId, 'tenant.self_service_onboarded', [
                'subdomain' => $subdomain,
                'owner_email' => $ownerEmail,
                'plan_code' => $planCode
            ]);

            $pdo->commit();
            redirect_to('/login.php?store=' . urlencode($subdomain));
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to create tenant right now. Please try again.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Store · <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="card" style="max-width:760px;margin:30px auto">
        <h1>Create Your Store</h1>
        <p class="small">Self-service onboarding creates your tenant, subscription, and owner account.</p>
        <?php if ($error): ?><p class="badge danger"><?= h($error) ?></p><?php endif; ?>

        <form method="post" class="grid">
            <?= csrf_field() ?>
            <div>
                <label class="small">Store name</label>
                <input name="name" required value="<?= h(post('name')) ?>">
            </div>
            <div>
                <label class="small">Store / subdomain code</label>
                <input name="subdomain" required placeholder="my-store" value="<?= h(post('subdomain')) ?>">
            </div>
            <div>
                <label class="small">Owner email</label>
                <input type="email" name="owner_email" required value="<?= h(post('owner_email')) ?>">
            </div>
            <div>
                <label class="small">Owner username</label>
                <input name="owner_username" required value="<?= h(post('owner_username')) ?>">
            </div>
            <div>
                <label class="small">Owner password</label>
                <input type="password" name="owner_password" required>
            </div>
            <div>
                <label class="small">Initial plan</label>
                <select name="plan_code">
                    <option value="free" <?= post('plan_code', 'free') === 'free' ? 'selected' : '' ?>>free</option>
                    <option value="starter" <?= post('plan_code') === 'starter' ? 'selected' : '' ?>>starter</option>
                </select>
            </div>
            <div class="row">
                <button type="submit">Create Store</button>
                <a href="/login.php">Back to login</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>

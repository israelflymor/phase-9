<?php
require_once __DIR__ . '/helpers.php';

function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['__initiated'])) {
        session_regenerate_id(true);
        $_SESSION['__initiated'] = time();
    } elseif (time() - (int)$_SESSION['__initiated'] > 900) {
        session_regenerate_id(true);
        $_SESSION['__initiated'] = time();
    }
}

function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf_or_die($isJson = false) {
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valid = is_string($token)
        && !empty($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
    if (!$valid) {
        if ($isJson) {
            api_error('Invalid CSRF token', 419, 'invalid_csrf');
        }
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function get_client_ip() {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded) {
        $parts = explode(',', $forwarded);
        return trim((string)$parts[0]);
    }
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
}

function rate_limit_or_die(PDO $pdo, $scope, $identifier, $maxAttempts, $windowSeconds, $isJson = false) {
    $now = time();
    $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds);

    $stmt = $pdo->prepare('
        INSERT INTO rate_limits (scope, identifier, attempts, window_started_at, updated_at)
        VALUES (?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            attempts = IF(window_started_at < ?, 1, attempts + 1),
            window_started_at = IF(window_started_at < ?, NOW(), window_started_at),
            updated_at = NOW()
    ');
    $stmt->execute([$scope, $identifier, $windowStart, $windowStart]);

    $stmt = $pdo->prepare('SELECT attempts, window_started_at FROM rate_limits WHERE scope = ? AND identifier = ? LIMIT 1');
    $stmt->execute([$scope, $identifier]);
    $row = $stmt->fetch();

    if (!$row) return;

    if ((int)$row['attempts'] > (int)$maxAttempts) {
        if ($isJson) {
            api_error('Too many requests. Please try again later.', 429, 'rate_limited');
        }
        http_response_code(429);
        exit('Too many requests. Please try again later.');
    }
}

function verify_stripe_signature_or_die($payload, $signatureHeader) {
    if (!STRIPE_WEBHOOK_SECRET || strpos(STRIPE_WEBHOOK_SECRET, 'replace-with-') === 0) {
        api_error('Webhook secret is not configured', 500, 'webhook_not_configured');
    }

    $parts = [];
    foreach (explode(',', (string)$signatureHeader) as $part) {
        $pieces = explode('=', trim($part), 2);
        if (count($pieces) === 2) $parts[$pieces[0]] = $pieces[1];
    }
    $timestamp = $parts['t'] ?? '';
    $signature = $parts['v1'] ?? '';

    if (!$timestamp || !$signature) {
        api_error('Invalid Stripe signature header', 401, 'invalid_webhook_signature');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, STRIPE_WEBHOOK_SECRET);
    if (!hash_equals($expected, $signature)) {
        api_error('Stripe signature verification failed', 401, 'invalid_webhook_signature');
    }
}

function verify_paystack_signature_or_die($payload, $signatureHeader) {
    if (!PAYSTACK_WEBHOOK_SECRET || strpos(PAYSTACK_WEBHOOK_SECRET, 'replace-with-') === 0) {
        api_error('Webhook secret is not configured', 500, 'webhook_not_configured');
    }
    $expected = hash_hmac('sha512', $payload, PAYSTACK_WEBHOOK_SECRET);
    if (!$signatureHeader || !hash_equals($expected, (string)$signatureHeader)) {
        api_error('Paystack signature verification failed', 401, 'invalid_webhook_signature');
    }
}
?>

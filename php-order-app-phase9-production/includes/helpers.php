<?php
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect_to($p){ header('Location: '.$p); exit; }
function post($k,$d=''){ return trim($_POST[$k] ?? $d); }
function getv($k,$d=''){ return trim($_GET[$k] ?? $d); }
function json_response($d,$c=200){ http_response_code($c); header('Content-Type: application/json'); echo json_encode($d); exit; }
function random_key($prefix='sk_live_'){ return $prefix . bin2hex(random_bytes(16)); }

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_or_die() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $given = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['_csrf_token'] ?? '';
    if (!is_string($given) || !is_string($expected) || !$expected || !hash_equals($expected, $given)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function normalize_store_code($value) {
    $clean = strtolower(trim((string)$value));
    $clean = preg_replace('/[^a-z0-9\-]/', '', $clean);
    return trim($clean, '-');
}
?>

<?php
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect_to($p){ header('Location: '.$p); exit; }
function post($k,$d=''){ return trim($_POST[$k] ?? $d); }
function getv($k,$d=''){ return trim($_GET[$k] ?? $d); }
function json_response($d,$c=200){
    http_response_code($c);
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_success($data = [], $code = 200) {
    json_response(['success' => true, 'data' => $data], $code);
}
function api_error($message, $code = 400, $errorCode = 'bad_request', $details = null) {
    $payload = ['success' => false, 'error' => ['code' => $errorCode, 'message' => $message]];
    if ($details !== null) $payload['error']['details'] = $details;
    json_response($payload, $code);
}
function random_key($prefix='sk_live_'){ return $prefix . bin2hex(random_bytes(16)); }
?>

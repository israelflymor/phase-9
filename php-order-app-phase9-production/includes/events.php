<?php
function emit_event(PDO $pdo, $tenantId, $eventName, array $payload = []) {
    $stmt = $pdo->prepare('INSERT INTO event_logs (tenant_id, event_name, payload_json) VALUES (?, ?, ?)');
    $stmt->execute([$tenantId ?: null, $eventName, json_encode($payload)]);
}
?>

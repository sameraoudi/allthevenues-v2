<?php
declare(strict_types=1);

/**
 * Audit log helper. Records admin actions to the audit_log table.
 * Never throws — a logging failure must not abort the action it records.
 */

require_once __DIR__ . '/ratelimit.php';   // client_ip()

/**
 * @param int|null   $userId     acting admin (from session)
 * @param string     $action     e.g. 'enquiry.status', 'enquiry.note', 'enquiry.forward'
 * @param string     $entityType e.g. 'enquiry'
 * @param int|null   $entityId
 * @param mixed      $old        old value (json-encoded); null to omit
 * @param mixed      $new        new value (json-encoded); null to omit
 */
function audit_log(
    PDO $pdo,
    ?int $userId,
    string $action,
    string $entityType,
    ?int $entityId,
    $old = null,
    $new = null
): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_value_json, new_value_json, ip_address)
             VALUES (:uid, :action, :etype, :eid, :old, :new, :ip)'
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':etype'  => $entityType,
            ':eid'    => $entityId,
            ':old'    => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            ':new'    => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            ':ip'     => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('audit_log failed (' . $action . '): ' . $e->getMessage());
    }
}

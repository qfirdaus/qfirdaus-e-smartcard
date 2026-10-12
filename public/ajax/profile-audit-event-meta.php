<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

@ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json; charset=utf-8');

function auditEventMetaHumanizeField(string $field): string
{
    $field = trim($field);
    if ($field === '') {
        return (string)__('profile_audit_field_generic');
    }

    $field = preg_replace('/^f_/', '', $field) ?? $field;
    $field = str_replace(['_', '-'], ' ', $field);
    $field = preg_replace('/\s+/', ' ', $field) ?? $field;
    return ucwords(trim($field));
}

function auditEventMetaLooksSensitive(string $field): bool
{
    $field = strtolower(trim($field));
    if ($field === '') {
        return false;
    }

    foreach ([
        'password', 'token', 'csrf', 'cookie', 'secret', 'session', 'request_id',
        'login_id', 'user_agent', 'ip', 'fingerprint', 'auth', 'key'
    ] as $needle) {
        if (str_contains($field, $needle)) {
            return true;
        }
    }

    return false;
}

function auditEventMetaSanitizeMeta(array $event, ?array $decodedMeta): array
{
    $summary = [
        'occurred_at' => (string)($event['occurred_at'] ?? ''),
        'event_type' => (string)($event['event_type'] ?? ''),
        'outcome' => (string)($event['outcome'] ?? ''),
        'severity' => (string)($event['severity'] ?? ''),
        'target_type' => (string)($event['target_type'] ?? ''),
        'target_label' => (string)($event['target_label'] ?? ''),
        'message' => (string)($event['message'] ?? ''),
    ];

    if (is_array($decodedMeta)) {
        foreach (['module', 'action', 'page', 'section'] as $key) {
            if (!empty($decodedMeta[$key]) && !is_array($decodedMeta[$key])) {
                $summary[$key] = (string)$decodedMeta[$key];
            }
        }
    }

    return array_filter($summary, static fn($value) => $value !== '');
}

function auditEventMetaSanitizeChangeSets(array $changeSets): array
{
    $summaryRows = [];

    foreach ($changeSets as $changeSet) {
        $reason = trim((string)($changeSet['change_reason'] ?? ''));
        $fieldChanges = is_array($changeSet['field_changes'] ?? null) ? $changeSet['field_changes'] : [];

        if ($fieldChanges === []) {
            $summaryRows[] = [
                'field' => auditEventMetaHumanizeField((string)($changeSet['target_type'] ?? __('profile_audit_change_generic'))),
                'before' => (string)__('profile_audit_value_recorded'),
                'after' => $reason !== '' ? $reason : (string)__('profile_audit_value_updated'),
            ];
            continue;
        }

        foreach ($fieldChanges as $fieldChange) {
            $fieldName = (string)($fieldChange['field'] ?? '');
            $isSensitive = !empty($fieldChange['is_sensitive']) || auditEventMetaLooksSensitive($fieldName);
            $hint = trim((string)($fieldChange['diff_hint'] ?? ''));

            $afterLabel = $hint !== ''
                ? $hint
                : ($reason !== '' ? $reason : ($isSensitive ? (string)__('profile_audit_change_recorded') : (string)__('profile_audit_value_updated')));

            $summaryRows[] = [
                'field' => auditEventMetaHumanizeField($fieldName),
                'before' => (string)__('profile_audit_value_recorded'),
                'after' => $isSensitive ? (string)__('profile_audit_value_hidden') : $afterLabel,
            ];
        }
    }

    return $summaryRows;
}

$eventId = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_event_id', 'message' => (string)__('profile_audit_invalid_event_id')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userModel = new User($pdo);
    $profile = $userModel->getProfile((string)($_SESSION['f_stafID'] ?? ''));
    $isSuperAdmin = $profile && function_exists('is_user_super_admin') && is_user_super_admin($profile, $pdo);

    $sql = "
        SELECT
            id,
            occurred_at,
            request_id,
            session_id,
            user_id,
            actor_label,
            event_type,
            severity,
            outcome,
            target_type,
            target_id,
            target_label,
            message,
            meta
        FROM audit_event
        WHERE id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found', 'message' => (string)__('profile_audit_event_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decodedMeta = null;
    if (!empty($event['meta'])) {
        $decodedMeta = json_decode((string)$event['meta'], true);
        if (!is_array($decodedMeta)) {
            $decodedMeta = null;
        }
    }

    $changeSets = [];
    $sqlCS = "SELECT id, target_type, target_id, change_reason, meta FROM audit_change_set WHERE event_id = :eventId ORDER BY id ASC";
    $stmtCS = $pdo->prepare($sqlCS);
    $stmtCS->execute([':eventId' => $eventId]);
    while ($cs = $stmtCS->fetch(PDO::FETCH_ASSOC)) {
        $csId = (int)($cs['id'] ?? 0);
        $csMeta = null;
        if (!empty($cs['meta'])) {
            $decodedCsMeta = json_decode((string)$cs['meta'], true);
            $csMeta = is_array($decodedCsMeta) ? $decodedCsMeta : null;
        }

        $fields = [];
        if ($csId > 0) {
            $sqlF = "SELECT field, old_value, new_value, data_type, is_sensitive, diff_hint FROM audit_change_field WHERE change_set_id = :changeSetId ORDER BY id ASC";
            $stmtF = $pdo->prepare($sqlF);
            $stmtF->execute([':changeSetId' => $csId]);
            while ($f = $stmtF->fetch(PDO::FETCH_ASSOC)) {
                $fields[] = [
                    'field' => (string)($f['field'] ?? ''),
                    'old_value' => $f['old_value'],
                    'new_value' => $f['new_value'],
                    'data_type' => (string)($f['data_type'] ?? 'string'),
                    'is_sensitive' => !empty($f['is_sensitive']),
                    'diff_hint' => $f['diff_hint'] ? (string)$f['diff_hint'] : null,
                ];
            }
        }

        $changeSets[] = [
            'id' => $csId,
            'target_type' => (string)($cs['target_type'] ?? ''),
            'target_id' => (string)($cs['target_id'] ?? ''),
            'change_reason' => $cs['change_reason'] ? (string)$cs['change_reason'] : null,
            'meta' => $csMeta,
            'field_changes' => $fields,
        ];
    }

    if ($isSuperAdmin) {
        echo json_encode([
            'meta' => $decodedMeta,
            'change_sets' => $changeSets,
            'allow_full_metadata' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'meta' => auditEventMetaSanitizeMeta($event, $decodedMeta),
        'change_sets' => auditEventMetaSanitizeChangeSets($changeSets),
        'allow_full_metadata' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[profile-audit-event-meta] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => (string)__('profile_audit_server_error')], JSON_UNESCAPED_UNICODE);
    exit;
}

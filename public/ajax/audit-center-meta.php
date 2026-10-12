<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../controllers/AuditCenterController.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

function ac(string $key, string $fallback): string
{
    $value = __('audit_center_' . $key);
    return ($value === 'audit_center_' . $key || $value === null || $value === '') ? $fallback : (string)$value;
}

/**
 * @return array<string,mixed>|null
 */
function fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @return array<int,array<string,mixed>>
 */
function fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function decode_json_field($value): mixed
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function normalize_for_json(mixed $value): mixed
{
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = normalize_for_json($item);
        }
        return $normalized;
    }

    if (is_object($value)) {
        return normalize_for_json((array)$value);
    }

    if (is_string($value)) {
        if ($value === '') {
            return $value;
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        return 'hex:' . strtoupper(bin2hex($value));
    }

    return $value;
}

function sanitize_audit_data(mixed $value, ?string $parentKey = null): mixed
{
    $sensitiveKey = $parentKey !== null && preg_match('/(?:password|passwd|token|secret|authorization|cookie|api[_-]?key|credential)/i', $parentKey);
    if ($sensitiveKey) {
        return '[MASKED]';
    }
    if (is_array($value)) {
        if (!empty($value['is_sensitive'])) {
            if (array_key_exists('old_value', $value)) $value['old_value'] = '[MASKED]';
            if (array_key_exists('new_value', $value)) $value['new_value'] = '[MASKED]';
        }
        foreach ($value as $key => $item) {
            $value[$key] = sanitize_audit_data($item, (string)$key);
        }
        return $value;
    }
    if (is_object($value)) {
        return sanitize_audit_data((array)$value, $parentKey);
    }
    if (is_string($value) && strlen($value) <= 1048576) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return sanitize_audit_data($decoded, $parentKey);
        }
    }
    return $value;
}

/**
 * @param array<string,mixed> $record
 * @return array<int,array<string,mixed>>
 */
function build_sections_for_event(PDO $pdo, array $record): array
{
    $sections = [];
    $meta = decode_json_field($record['meta'] ?? null);
    if ($meta !== null) {
        $sections[] = ['label' => ac('meta_section_event_meta', 'Event Meta'), 'data' => $meta];
    }

    $changeSets = fetch_all($pdo, "
        SELECT id, target_type, target_id, change_reason, meta
        FROM audit_change_set
        WHERE event_id = :event_id
        ORDER BY id ASC
        LIMIT 50
    ", [':event_id' => (int)($record['id'] ?? 0)]);

    foreach ($changeSets as &$changeSet) {
        $changeSet['meta'] = decode_json_field($changeSet['meta'] ?? null);
        $changeSet['field_changes'] = fetch_all($pdo, "
            SELECT field, old_value, new_value, data_type, is_sensitive, diff_hint
            FROM audit_change_field
            WHERE change_set_id = :change_set_id
            ORDER BY id ASC
            LIMIT 200
        ", [':change_set_id' => (int)($changeSet['id'] ?? 0)]);
    }
    unset($changeSet);

    if ($changeSets !== []) {
        $sections[] = ['label' => ac('meta_section_change_sets', 'Related Change Sets'), 'data' => $changeSets];
    }

    return $sections;
}

try {
    if (function_exists('checkRateLimit') && !checkRateLimit('audit_center_meta', 60, 60)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => ac('meta_rate_limited', 'Terlalu banyak permintaan metadata.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $controller = new AuditCenterController();
    if (!$controller->isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => ac('access_denied_text', 'Halaman Audit Center hanya boleh dicapai oleh super admin.')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $kind = strtolower(trim((string)($_GET['kind'] ?? '')));
    $pdo = Database::pdoMysql();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $title = '';
    $subtitle = '';
    $record = null;
    $sections = [];

    if ($kind === 'event') {
        $id = (int)($_GET['id'] ?? 0);
        $record = fetch_one($pdo, "SELECT * FROM audit_event WHERE id = :id LIMIT 1", [':id' => $id]);
        $title = ac('meta_title_event', 'Event Metadata');
        $subtitle = 'Event ID: ' . $id;
        if ($record) {
            $sections = build_sections_for_event($pdo, $record);
        }
    } elseif ($kind === 'request') {
        $requestId = trim((string)($_GET['request_id'] ?? ''));
        $record = fetch_one($pdo, "SELECT * FROM audit_request WHERE request_id = :request_id LIMIT 1", [':request_id' => $requestId]);
        $title = ac('meta_title_request', 'Request Metadata');
        $subtitle = 'Request ID: ' . $requestId;
        if ($record && !empty($record['request_id'])) {
            $relatedEvents = fetch_all($pdo, "
                SELECT id, occurred_at, event_type, outcome, severity, message
                FROM audit_event
                WHERE request_id = :request_id
                ORDER BY occurred_at DESC, id DESC
                LIMIT 100
            ", [':request_id' => (string)$record['request_id']]);
            if ($relatedEvents !== []) {
                $sections[] = ['label' => ac('meta_section_related_events', 'Related Events'), 'data' => $relatedEvents];
            }
        }
    } elseif ($kind === 'session') {
        $sessionId = trim((string)($_GET['session_id'] ?? ''));
        $record = fetch_one($pdo, "SELECT * FROM audit_session WHERE session_id = :session_id LIMIT 1", [':session_id' => $sessionId]);
        $title = ac('meta_title_session', 'Session Metadata');
        $subtitle = 'Session ID: ' . $sessionId;
        if ($record && !empty($record['session_id'])) {
            $relatedEvents = fetch_all($pdo, "
                SELECT id, occurred_at, event_type, outcome, severity, message
                FROM audit_event
                WHERE session_id = :session_id
                ORDER BY occurred_at DESC, id DESC
                LIMIT 50
            ", [':session_id' => (string)$record['session_id']]);
            if ($relatedEvents !== []) {
                $sections[] = ['label' => ac('meta_section_related_events', 'Related Events'), 'data' => $relatedEvents];
            }
        }
    } elseif ($kind === 'change') {
        $changeSetId = (int)($_GET['change_set_id'] ?? 0);
        $record = fetch_one($pdo, "
            SELECT cs.*, e.id AS event_id, e.event_type, e.actor_label, e.login_id, e.message, e.meta AS event_meta, e.occurred_at
            FROM audit_change_set cs
            LEFT JOIN audit_event e ON e.id = cs.event_id
            WHERE cs.id = :id
            LIMIT 1
        ", [':id' => $changeSetId]);
        $title = ac('meta_title_change', 'Change Set Metadata');
        $subtitle = 'Change Set ID: ' . $changeSetId;
        if ($record) {
            $eventMeta = decode_json_field($record['event_meta'] ?? null);
            if ($eventMeta !== null) {
                $sections[] = ['label' => ac('meta_section_event_meta', 'Event Meta'), 'data' => $eventMeta];
            }
            $changeMeta = decode_json_field($record['meta'] ?? null);
            if ($changeMeta !== null) {
                $sections[] = ['label' => ac('meta_section_change_meta', 'Change Set Meta'), 'data' => $changeMeta];
            }
            $fieldChanges = fetch_all($pdo, "
                SELECT field, old_value, new_value, data_type, is_sensitive, diff_hint
                FROM audit_change_field
                WHERE change_set_id = :change_set_id
                ORDER BY id ASC
                LIMIT 200
            ", [':change_set_id' => $changeSetId]);
            if ($fieldChanges !== []) {
                $sections[] = ['label' => ac('meta_section_field_changes', 'Field Changes'), 'data' => $fieldChanges];
            }
        }
    } elseif ($kind === 'lockout') {
        $loginId = trim((string)($_GET['login_id'] ?? ''));
        $record = fetch_one($pdo, "SELECT * FROM tbl_auth_login_lockout WHERE TRIM(f_loginID) = :login_id LIMIT 1", [':login_id' => $loginId]);
        $title = ac('meta_title_lockout', 'Lockout Metadata');
        $subtitle = 'Login ID: ' . $loginId;
    } elseif ($kind === 'throttle') {
        $scopeType = trim((string)($_GET['scope_type'] ?? ''));
        $scopeKey = trim((string)($_GET['scope_key'] ?? ''));
        $record = fetch_one($pdo, "SELECT * FROM tbl_auth_login_throttle WHERE f_scope_type = :scope_type AND f_scope_key = :scope_key LIMIT 1", [
            ':scope_type' => $scopeType,
            ':scope_key' => $scopeKey,
        ]);
        $title = ac('meta_title_throttle', 'Throttle Metadata');
        $subtitle = trim($scopeType . ' : ' . $scopeKey);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => ac('meta_invalid_kind', 'Jenis metadata tidak sah.')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => ac('meta_not_found', 'Metadata rekod tidak ditemui.')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $safePayload = sanitize_audit_data([
        'success' => true,
        'title' => $title,
        'subtitle' => $subtitle,
        'record' => $record,
        'sections' => $sections,
    ]);

    if (function_exists('audit_event')) {
        audit_event([
            'event_type' => 'AUDIT_READ', 'severity' => 'INFO', 'outcome' => 'SUCCESS',
            'target_type' => 'audit_metadata', 'target_id' => (string)($subtitle ?: $kind),
            'target_label' => $title, 'message' => 'Audit metadata viewed',
            'meta' => ['kind' => $kind],
        ]);
    }

    echo json_encode(normalize_for_json($safePayload), JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[audit-center-meta] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => ac('meta_server_error', 'Ralat sistem semasa memuatkan metadata audit.')], JSON_UNESCAPED_UNICODE);
    exit;
}

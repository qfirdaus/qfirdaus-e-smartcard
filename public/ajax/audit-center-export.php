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

function ac(string $key, string $fallback): string
{
    $value = __('audit_center_' . $key);
    return ($value === 'audit_center_' . $key || $value === null || $value === '') ? $fallback : (string)$value;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function normalize_export_rows(string $tab, string $securitySubtab, array $rows): array
{
    $normalized = [];
    foreach ($rows as $row) {
        if ($tab === 'requests') {
            $normalized[] = [
                'id' => $row['id'] ?? '',
                'request_id' => $row['request_id'] ?? '',
                'session_id' => $row['session_id'] ?? '',
                'login_id' => $row['login_id'] ?? '',
                'user_id' => $row['user_id'] ?? '',
                'method' => $row['method'] ?? '',
                'route' => $row['route'] ?? '',
                'status_code' => $row['status_code'] ?? '',
                'started_at' => $row['started_at'] ?? '',
                'ended_at' => $row['ended_at'] ?? '',
                'duration_ms' => $row['duration_ms'] ?? '',
                'ip_text' => $row['ip_text'] ?? '',
            ];
            continue;
        }

        if ($tab === 'sessions') {
            $normalized[] = [
                'id' => $row['id'] ?? '',
                'session_id' => $row['session_id'] ?? '',
                'login_id' => $row['login_id'] ?? '',
                'display_name' => $row['display_name'] ?? '',
                'user_summary' => trim((string)($row['display_name'] ?? '')) !== ''
                    ? trim((string)$row['display_name']) . (trim((string)($row['login_id'] ?? '')) !== '' ? ' (' . trim((string)$row['login_id']) . ')' : '')
                    : (string)($row['login_id'] ?? $row['user_nopekerja'] ?? ''),
                'user_id' => $row['user_id'] ?? '',
                'legacy_identifier' => $row['user_nopekerja'] ?? '',
                'started_at' => $row['started_at'] ?? '',
                'ended_at' => $row['ended_at'] ?? '',
                'duration_seconds' => $row['duration_seconds'] ?? '',
                'ip_text' => $row['ip_text'] ?? '',
                'user_agent' => $row['user_agent'] ?? '',
            ];
            continue;
        }

        if ($tab === 'changes') {
            $fieldChanges = is_array($row['field_changes'] ?? null) ? $row['field_changes'] : [];
            foreach ($fieldChanges as &$fieldChange) {
                if (is_array($fieldChange) && !empty($fieldChange['is_sensitive'])) {
                    $fieldChange['old_value'] = '[MASKED]';
                    $fieldChange['new_value'] = '[MASKED]';
                }
            }
            unset($fieldChange);
            $normalized[] = [
                'change_set_id' => $row['id'] ?? '',
                'event_id' => $row['event_id'] ?? '',
                'occurred_at' => $row['occurred_at'] ?? '',
                'event_type' => $row['event_type'] ?? '',
                'actor_label' => $row['actor_label'] ?? '',
                'login_id' => $row['login_id'] ?? '',
                'target_type' => $row['target_type'] ?? '',
                'target_id' => $row['target_id'] ?? '',
                'change_reason' => $row['change_reason'] ?? '',
                'field_count' => $row['field_count'] ?? '',
                'message' => $row['message'] ?? '',
                'field_changes_json' => json_encode($fieldChanges, JSON_UNESCAPED_UNICODE),
            ];
            continue;
        }

        if ($tab === 'security') {
            if ($securitySubtab === 'lockouts') {
                $normalized[] = [
                    'id' => $row['id'] ?? '',
                    'login_id' => $row['f_loginID'] ?? '',
                    'failed_attempts' => $row['f_failed_attempts'] ?? '',
                    'locked_until' => $row['f_locked_until'] ?? '',
                    'last_failed_at' => $row['f_last_failed_at'] ?? '',
                    'last_ip' => $row['f_last_ip'] ?? '',
                ];
            } elseif ($securitySubtab === 'throttles') {
                $normalized[] = [
                    'id' => $row['id'] ?? '',
                    'scope_type' => $row['f_scope_type'] ?? '',
                    'scope_key' => $row['f_scope_key'] ?? '',
                    'failed_attempts' => $row['f_failed_attempts'] ?? '',
                    'locked_until' => $row['f_locked_until'] ?? '',
                    'last_failed_at' => $row['f_last_failed_at'] ?? '',
                    'last_ip' => $row['f_last_ip'] ?? '',
                ];
            } else {
                $normalized[] = [
                    'id' => $row['id'] ?? '',
                    'occurred_at' => $row['occurred_at'] ?? '',
                    'login_id' => $row['login_id'] ?? '',
                    'actor_label' => $row['actor_label'] ?? '',
                    'ip_text' => $row['ip_text'] ?? '',
                    'event_type' => $row['event_type'] ?? '',
                    'severity' => $row['severity'] ?? '',
                    'outcome' => $row['outcome'] ?? '',
                    'message' => $row['message'] ?? '',
                ];
            }
            continue;
        }

        $normalized[] = [
            'id' => $row['id'] ?? '',
            'occurred_at' => $row['occurred_at'] ?? '',
            'request_id' => $row['request_id'] ?? '',
            'session_id' => $row['session_id'] ?? '',
            'user_id' => $row['user_id'] ?? '',
            'login_id' => $row['login_id'] ?? '',
            'actor_label' => $row['actor_label'] ?? '',
            'ip_text' => $row['ip_text'] ?? '',
            'event_type' => $row['event_type'] ?? '',
            'severity' => $row['severity'] ?? '',
            'outcome' => $row['outcome'] ?? '',
            'target_type' => $row['target_type'] ?? '',
            'target_id' => $row['target_id'] ?? '',
            'target_label' => $row['target_label'] ?? '',
            'message' => $row['message'] ?? '',
        ];
    }

    return $normalized;
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function stream_csv(array $rows, string $filename): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");

    $headers = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $headers, true)) {
                $headers[] = $key;
            }
        }
    }
    if ($headers === []) {
        $headers = ['message'];
        fputcsv($out, $headers, ',', '"', '');
        fputcsv($out, ['No records found'], ',', '"', '');
        fclose($out);
        exit;
    }

    fputcsv($out, $headers, ',', '"', '');
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $cell = (string)$value;
            if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell)) {
                $cell = "'" . $cell;
            }
            $line[] = $cell;
        }
        fputcsv($out, $line, ',', '"', '');
    }

    fclose($out);
    exit;
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function stream_json(array $rows, string $filename): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$controller = new AuditCenterController();
if (!$controller->isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo ac('access_denied_text', 'Audit Center is available to Super Admin only.');
    exit;
}

if (function_exists('checkRateLimit') && !checkRateLimit('audit_center_export', 10, 60)) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    echo ac('export_rate_limited', 'Too many export requests. Please wait.');
    exit;
}

$tab = strtolower(trim((string)($_GET['tab'] ?? 'events')));
$securitySubtab = strtolower(trim((string)($_GET['security_subtab'] ?? 'events')));
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
if (!in_array($format, ['csv', 'json'], true)) {
    $format = 'csv';
}
$search = trim((string)($_GET['q'] ?? ''));
$filters = $controller->normalizeFilters($_GET);
$exportLimit = max(100, min(2000, (int)($_GET['export_limit'] ?? 2000)));

$export = $controller->getExportData($tab, $securitySubtab, $search, $filters, $exportLimit);
$rows = normalize_export_rows($export['tab'], $export['security_subtab'], $export['rows']);

if (function_exists('audit_event')) {
    audit_event([
        'event_type' => 'AUDIT_EXPORT', 'severity' => 'INFO', 'outcome' => 'SUCCESS',
        'target_type' => 'audit_export', 'target_id' => $export['tab'],
        'target_label' => strtoupper($format) . ' audit export', 'message' => 'Audit data exported',
        'meta' => ['tab' => $export['tab'], 'security_subtab' => $export['security_subtab'], 'format' => $format, 'row_count' => count($rows)],
    ]);
}

$slug = $export['tab'];
if ($export['tab'] === 'security') {
    $slug .= '-' . $export['security_subtab'];
}
$timestamp = date('Ymd-His');
$baseName = 'audit-center-' . $slug . '-' . $timestamp;

if ($format === 'json') {
    stream_json($rows, $baseName . '.json');
}

stream_csv($rows, $baseName . '.csv');

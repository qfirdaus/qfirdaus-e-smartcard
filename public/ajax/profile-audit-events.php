<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

// Capture stray output early to avoid breaking JSON
@ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json; charset=utf-8');

$controller = new ProfileController();
$limit = defined('PROFILE_CONFIG') && isset(PROFILE_CONFIG['AUDIT_EVENTS_LIMIT']) ? PROFILE_CONFIG['AUDIT_EVENTS_LIMIT'] : 30;
$rows = $controller->getAuditEvents((int)$limit, false);
$pdo = Database::getInstance()->getConnection();
$profile = null;
$isSuperAdmin = false;
try {
    $userModel = new User($pdo);
    $profile = $userModel->getProfile((string)($_SESSION['f_stafID'] ?? ''));
    $isSuperAdmin = $profile && function_exists('is_user_super_admin') && is_user_super_admin($profile, $pdo);
} catch (Throwable $e) {
    $isSuperAdmin = false;
}

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function safeDateTime(?string $s): ?DateTime { if (empty($s)) return null; try { return new DateTime($s); } catch (Throwable $e) { return null; } }

$data = [];
foreach ($rows as $event) {
    $occurred = safeDateTime($event['occurred_at'] ?? null);
    $occurredText = $occurred ? $occurred->format('d/m/Y H:i:s') : '—';

    $ip = $event['ip_address'] ?? '—';

    $eventTypeIcon = 'ri-file-list-3-line';
    $eventTypeText = $event['event_type'] ?? '';
    $eventTypeLabels = [
        'CREATE' => (string)__('profile_audit_event_create'),
        'UPDATE' => (string)__('profile_audit_event_update'),
        'DELETE' => (string)__('profile_audit_event_delete'),
        'LOGIN' => (string)__('profile_audit_event_login'),
        'LOGOUT' => (string)__('profile_audit_event_logout'),
        'VIEW' => (string)__('profile_audit_event_view'),
    ];
    if ($eventTypeText === 'CREATE') $eventTypeIcon = 'ri-add-circle-line';
    elseif ($eventTypeText === 'UPDATE') $eventTypeIcon = 'ri-edit-box-line';
    elseif ($eventTypeText === 'DELETE') $eventTypeIcon = 'ri-delete-bin-line';
    elseif ($eventTypeText === 'LOGIN') $eventTypeIcon = 'ri-login-box-line';
    elseif ($eventTypeText === 'LOGOUT') $eventTypeIcon = 'ri-logout-box-line';
    elseif ($eventTypeText === 'VIEW') $eventTypeIcon = 'ri-eye-line';

    $activityDesc = $event['message'] ?? '';
    if (empty($activityDesc)) {
        $targetLabel = $event['target_label'] ?? $event['target_type'] ?? '';
        $translatedEventType = $eventTypeLabels[$eventTypeText] ?? $eventTypeText;
        if ($targetLabel) $activityDesc = $translatedEventType . ' - ' . $targetLabel;
        else $activityDesc = $translatedEventType;
    }

    $outcome = $event['outcome'] ?? '—';
    $outcomeClass = 'bg-secondary'; $outcomeIcon = 'ri-question-line';
    if ($outcome === 'SUCCESS') { $outcomeClass = 'bg-success'; $outcomeIcon = 'ri-checkbox-circle-line'; }
    elseif ($outcome === 'FAIL' || $outcome === 'FAILURE') { $outcomeClass = 'bg-danger'; $outcomeIcon = 'ri-close-circle-line'; }
    $outcomeLabel = match ($outcome) {
        'SUCCESS' => (string)__('profile_audit_outcome_success'),
        'FAIL', 'FAILURE' => (string)__('profile_audit_outcome_failure'),
        default => (string)__('profile_audit_outcome_unknown'),
    };

    $severity = $event['severity'] ?? '';
    $severityClass = 'bg-secondary';
    if ($severity === 'SECURITY') $severityClass = 'bg-danger';
    elseif ($severity === 'WARN' || $severity === 'WARNING') $severityClass = 'bg-warning';
    elseif ($severity === 'INFO') $severityClass = 'bg-info';
    $severityLabel = match ($severity) {
        'SECURITY' => (string)__('profile_audit_severity_security'),
        'WARN', 'WARNING' => (string)__('profile_audit_severity_warning'),
        'INFO' => (string)__('profile_audit_severity_info'),
        default => (string)__('profile_audit_severity_unknown'),
    };

    $hasMetadata = !empty($event['meta']) && is_array($event['meta']);
    $hasChangeSets = !empty($event['change_sets']) && is_array($event['change_sets']) && count($event['change_sets']) > 0;
    $hasAnyData = $hasMetadata || $hasChangeSets;

    if ($hasAnyData) {
        // Only include event id to avoid sending large meta/change-set payloads in table
        $eid = h((string)($event['id'] ?? ''));
        $btnTitle = $isSuperAdmin
            ? h((__('profile_audit_view_meta') ?: 'Lihat metadata'))
            : h((__('profile_audit_view_summary') ?: 'Lihat ringkasan audit'));
        $actions = '<button class="btn btn-sm btn-outline-primary btn-open-audit-meta" type="button" data-event-id="' . $eid . '" data-super-admin="' . ($isSuperAdmin ? '1' : '0') . '" title="' . $btnTitle . '"><i class="ri-information-line"></i></button>';
    } else {
        $actions = '<span class="text-muted">—</span>';
    }

    // Prefer event-level user identifiers before falling back to meta
    $userLabel = '—';
    $topLevelKeys = ['user_id','created_by','created_by_id','actor_id','performed_by','stafID','staf_id','staf','user','id'];
    foreach ($topLevelKeys as $k) {
        if (!empty($event[$k]) && !is_array($event[$k])) {
            $userLabel = h((string)$event[$k]);
            break;
        }
        // If top-level is array/object for 'user' or 'actor', try to extract name/id
        if (!empty($event[$k]) && is_array($event[$k])) {
            $u = $event[$k];
            if (!empty($u['full_name'])) { $userLabel = h((string)$u['full_name']); break; }
            if (!empty($u['name'])) { $userLabel = h((string)$u['name']); break; }
            if (!empty($u['id'])) { $userLabel = h((string)$u['id']); break; }
        }
    }

    // If still not found, try meta payload
    if ($userLabel === '—' && !empty($event['meta'])) {
        if (is_string($event['meta'])) {
            $decoded = json_decode($event['meta'], true);
            if (is_array($decoded)) $metaArr = $decoded; else $metaArr = null;
        } elseif (is_array($event['meta'])) {
            $metaArr = $event['meta'];
        } else {
            $metaArr = null;
        }

        if (!empty($metaArr) && is_array($metaArr)) {
            // Common keys for staff/full name or user id
            $nameKeys = ['nama_penuh','full_name','name','display_name','f_nama','f_nama_penuh','nama','staff_name','staf_nama'];
            $idKeys = ['user_id','stafID','staf_id','staf','id','nopek','no_pekerja','employee_no','staff_no'];
            foreach ($nameKeys as $k) { if (!empty($metaArr[$k])) { $userLabel = h((string)$metaArr[$k]); break; } }
            if ($userLabel === '—') {
                foreach ($idKeys as $k) { if (!empty($metaArr[$k])) { $userLabel = h((string)$metaArr[$k]); break; } }
            }
            // Fallback: if nested 'user' or 'actor'
            if ($userLabel === '—') {
                if (!empty($metaArr['user']) && is_array($metaArr['user'])) {
                    $u = $metaArr['user'];
                    if (!empty($u['full_name'])) $userLabel = h((string)$u['full_name']);
                    elseif (!empty($u['name'])) $userLabel = h((string)$u['name']);
                    elseif (!empty($u['id'])) $userLabel = h((string)$u['id']);
                } elseif (!empty($metaArr['actor']) && is_array($metaArr['actor'])) {
                    $u = $metaArr['actor'];
                    if (!empty($u['full_name'])) $userLabel = h((string)$u['full_name']);
                    elseif (!empty($u['name'])) $userLabel = h((string)$u['name']);
                    elseif (!empty($u['id'])) $userLabel = h((string)$u['id']);
                }
            }
        }
    }

    $data[] = [
        'user' => $userLabel,
        'occurred_at' => $occurredText,
        'ip' => '<code class="text-primary">' . h($ip) . '</code>',
        'activity' => '<i class="' . h($eventTypeIcon) . ' me-1"></i>' . h($activityDesc),
        'outcome' => '<span class="badge ' . h($outcomeClass) . '"><i class="' . h($outcomeIcon) . ' me-1"></i>' . h($outcomeLabel) . '</span>',
        'severity' => '<span class="badge ' . h($severityClass) . '">' . h($severityLabel) . '</span>',
        'actions' => $actions
    ];
}

// Capture stray output and include base64 for debugging
$stray = '';
try { $stray = (string)ob_get_clean(); } catch (Throwable $e) { $stray = ''; }

$payload = ['data' => $data];
if ($stray !== '') { $payload['_raw_output_b64'] = base64_encode($stray); }

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
exit;

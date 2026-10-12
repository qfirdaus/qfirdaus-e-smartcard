<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
    exit;
}

function menuSubgroupDeleteAuditActorLabel(): ?string
{
    $name = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
    $login = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
    return function_exists('audit_format_actor_label') ? audit_format_actor_label($name, $login) : ($name ? (string)$name : null);
}

function menuSubgroupDeleteAuditLog(array $subgroup, string $outcome = 'SUCCESS', string $severity = 'INFO'): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $actorLabel = menuSubgroupDeleteAuditActorLabel();
        $actionLabel = $outcome === 'DENIED' ? 'Menu subgroup delete denied' : 'Menu subgroup deleted';
        $message = function_exists('audit_format_message')
            ? audit_format_message($actionLabel, $actorLabel)
            : $actionLabel;

        audit_event([
            'event_type' => 'DELETE',
            'severity' => $severity,
            'outcome' => $outcome,
            'target_type' => 'menu_subgroup',
            'target_id' => (string)($subgroup['f_subgroupID'] ?? ''),
            'target_label' => (string)($subgroup['f_subgroupName_ms'] ?? $subgroup['f_subgroupCode'] ?? ''),
            'message' => $message,
            'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
            'session_id' => session_id() ?: null,
            'user_id' => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
            'actor_label' => $actorLabel,
            'meta' => [
                'modulID' => (int)($subgroup['f_modulID'] ?? 0),
                'subgroupCode' => (string)($subgroup['f_subgroupCode'] ?? ''),
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[menu-subgroup-delete] Audit logging failed: ' . $e->getMessage());
    }
}

try {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!checkRateLimit('menu_subgroup_delete', 20, 60)) {
        http_response_code(429);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $subgroupID = (int)($payload['subgroupID'] ?? 0);
    if ($subgroupID <= 0) {
        throw new InvalidArgumentException((string)__('userGroup_invalid_payload'));
    }

    $stmt = $pdo->prepare('SELECT f_subgroupID, f_modulID, f_subgroupCode, f_subgroupName_ms, f_subgroupName_en, f_icon, f_order, f_status FROM tbl_m_menu_subgroup WHERE f_subgroupID = ? LIMIT 1');
    $stmt->execute([$subgroupID]);
    $subgroup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subgroup) {
        throw new InvalidArgumentException((string)__('userGroup_subgroup_not_found'));
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_m_menu WHERE f_subgroupID = ?');
    $stmt->execute([$subgroupID]);
    if ((int)$stmt->fetchColumn() > 0) {
        menuSubgroupDeleteAuditLog($subgroup, 'DENIED', 'WARN');
        http_response_code(409);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_subgroup_in_use')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE tbl_m_menu_subgroup SET f_status = 0, f_updatedt = NOW(), f_updateby = ? WHERE f_subgroupID = ?');
    $stmt->execute([(string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? ''), $subgroupID]);
    menuSubgroupDeleteAuditLog($subgroup);

    GroupDataCache::clear('menu_list_');
    GroupDataCache::clear('group_perms_');
    clearSidebarNavigationCaches();

    echo json_encode(['error' => false, 'ok' => true, 'message' => (string)__('userGroup_subgroup_delete_success')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    error_log('[menu-subgroup-delete] ' . $e->getMessage());
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

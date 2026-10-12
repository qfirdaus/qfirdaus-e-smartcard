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

function menuSubgroupAuditActorLabel(): ?string
{
    $name = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
    $login = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
    return function_exists('audit_format_actor_label') ? audit_format_actor_label($name, $login) : ($name ? (string)$name : null);
}

function menuSubgroupAuditLog(string $eventType, int $subgroupID, ?array $oldData, array $newData): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $actorLabel = menuSubgroupAuditActorLabel();
        $actionLabel = $eventType === 'CREATE' ? 'Menu subgroup created' : 'Menu subgroup updated';
        $message = function_exists('audit_format_message')
            ? audit_format_message($actionLabel, $actorLabel)
            : $actionLabel;
        $targetLabel = (string)($newData['f_subgroupName_ms'] ?? $oldData['f_subgroupName_ms'] ?? ('Subgroup ' . $subgroupID));

        $eventId = audit_event([
            'event_type' => $eventType,
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'menu_subgroup',
            'target_id' => (string)$subgroupID,
            'target_label' => $targetLabel,
            'message' => $message,
            'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
            'session_id' => session_id() ?: null,
            'user_id' => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
            'actor_label' => $actorLabel,
            'meta' => [
                'modulID' => (int)($newData['f_modulID'] ?? 0),
                'subgroupCode' => (string)($newData['f_subgroupCode'] ?? ''),
                'status' => (int)($newData['f_status'] ?? 0),
            ],
        ]);

        if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
            return;
        }

        $changeSetId = audit_begin_change($eventId, 'menu_subgroup', (string)$subgroupID, $actionLabel);
        if (!$changeSetId) {
            return;
        }

        foreach (['f_modulID', 'f_subgroupCode', 'f_subgroupName_ms', 'f_subgroupName_en', 'f_icon', 'f_order', 'f_status'] as $field) {
            $oldValue = $oldData[$field] ?? null;
            $newValue = $newData[$field] ?? null;
            if ((string)$oldValue !== (string)$newValue) {
                $type = in_array($field, ['f_modulID', 'f_order', 'f_status'], true) ? 'integer' : 'string';
                audit_change($changeSetId, $field, $oldValue === null ? null : (string)$oldValue, $newValue === null ? null : (string)$newValue, $type, false);
            }
        }
    } catch (Throwable $e) {
        error_log('[menu-subgroup-save] Audit logging failed: ' . $e->getMessage());
    }
}

try {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!checkRateLimit('menu_subgroup_save', 20, 60)) {
        http_response_code(429);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $subgroupID = (int)($payload['subgroupID'] ?? 0);
    $modulID = (int)($payload['modulID'] ?? 0);
    $code = trim((string)($payload['code'] ?? ''));
    $nameMs = trim((string)($payload['name_ms'] ?? ''));
    $nameEn = trim((string)($payload['name_en'] ?? ''));
    $icon = trim((string)($payload['icon'] ?? 'ri-folder-2-line'));
    $order = (int)($payload['order'] ?? 0);
    $status = (int)($payload['status'] ?? 1) === 1 ? 1 : 0;
    $actor = (string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? '');

    if ($modulID <= 0 || $nameMs === '') {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_subgroup_required')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('SELECT f_modulID FROM tbl_m_modul WHERE f_modulID = ? LIMIT 1');
    $stmt->execute([$modulID]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_target_module_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($order <= 0) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(f_order),0)+1 FROM tbl_m_menu_subgroup WHERE f_modulID = ?');
        $stmt->execute([$modulID]);
        $order = (int)($stmt->fetchColumn() ?: 1);
    }

    $pdo->beginTransaction();
    $oldData = null;
    if ($subgroupID > 0) {
        $stmt = $pdo->prepare('SELECT f_subgroupID, f_modulID, f_subgroupCode, f_subgroupName_ms, f_subgroupName_en, f_icon, f_order, f_status FROM tbl_m_menu_subgroup WHERE f_subgroupID = ? FOR UPDATE');
        $stmt->execute([$subgroupID]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$oldData) {
            throw new RuntimeException((string)__('userGroup_subgroup_not_found'));
        }

        $stmt = $pdo->prepare("
            UPDATE tbl_m_menu_subgroup
            SET f_modulID = :modulID,
                f_subgroupCode = :code,
                f_subgroupName_ms = :nameMs,
                f_subgroupName_en = :nameEn,
                f_icon = :icon,
                f_order = :sortOrder,
                f_status = :status,
                f_updateby = :actor
            WHERE f_subgroupID = :subgroupID
        ");
        $stmt->execute([
            ':modulID' => $modulID,
            ':code' => $code !== '' ? $code : null,
            ':nameMs' => $nameMs,
            ':nameEn' => $nameEn !== '' ? $nameEn : null,
            ':icon' => $icon !== '' ? $icon : 'ri-folder-2-line',
            ':sortOrder' => $order,
            ':status' => $status,
            ':actor' => $actor,
            ':subgroupID' => $subgroupID,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_m_menu_subgroup
                (f_modulID, f_subgroupCode, f_subgroupName_ms, f_subgroupName_en, f_icon, f_order, f_status, f_insertby, f_updateby)
            VALUES
                (:modulID, :code, :nameMs, :nameEn, :icon, :sortOrder, :status, :insertby, :updateby)
        ");
        $stmt->execute([
            ':modulID' => $modulID,
            ':code' => $code !== '' ? $code : null,
            ':nameMs' => $nameMs,
            ':nameEn' => $nameEn !== '' ? $nameEn : null,
            ':icon' => $icon !== '' ? $icon : 'ri-folder-2-line',
            ':sortOrder' => $order,
            ':status' => $status,
            ':insertby' => $actor,
            ':updateby' => $actor,
        ]);
        $subgroupID = (int)$pdo->lastInsertId();
    }

    $newData = [
        'f_modulID' => $modulID,
        'f_subgroupCode' => $code !== '' ? $code : null,
        'f_subgroupName_ms' => $nameMs,
        'f_subgroupName_en' => $nameEn !== '' ? $nameEn : null,
        'f_icon' => $icon !== '' ? $icon : 'ri-folder-2-line',
        'f_order' => $order,
        'f_status' => $status,
    ];

    $pdo->commit();
    menuSubgroupAuditLog($oldData ? 'UPDATE' : 'CREATE', $subgroupID, $oldData, $newData);
    GroupDataCache::clear('menu_list_');
    GroupDataCache::clear('group_perms_');
    clearSidebarNavigationCaches();

    echo json_encode(['error' => false, 'ok' => true, 'subgroupID' => $subgroupID, 'message' => (string)__('userGroup_subgroup_save_success')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    error_log('[menu-subgroup-save] ' . $e->getMessage());
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

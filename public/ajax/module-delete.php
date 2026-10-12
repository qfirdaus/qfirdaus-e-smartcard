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
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => __('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isValidCsrfToken()) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => __('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!checkRateLimit('module_delete', 10, 60)) {
        http_response_code(429);
        echo json_encode(['error' => true, 'message' => __('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    if (!hasGroupManagePermission($pdo)) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => __('userGroup_delete_module_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $json = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $moduleID = (int)($json['moduleID'] ?? 0);
    if ($moduleID <= 0) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('userGroup_delete_module_invalid_id')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $modStmt = $pdo->prepare("
        SELECT f_modulID, COALESCE(NULLIF(f_modulName_ms,''), NULLIF(f_modulName_en,''), CONCAT('Modul ', f_modulID)) AS nama
        FROM tbl_m_modul
        WHERE f_modulID = :mid
        LIMIT 1
        FOR UPDATE
    ");
    $modStmt->execute([':mid' => $moduleID]);
    $module = $modStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$module) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => __('userGroup_delete_module_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $menuStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_m_menu WHERE f_modulID = :mid");
    $menuStmt->execute([':mid' => $moduleID]);
    $menuCount = (int)($menuStmt->fetchColumn() ?: 0);
    if ($menuCount > 0) {
        $pdo->rollBack();
        http_response_code(409);
        $tpl = (string)__('userGroup_delete_module_has_menus');
        echo json_encode([
            'error' => true,
            'message' => str_replace('{count}', (string)$menuCount, $tpl),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cleanGroups = $pdo->prepare("
        UPDATE tbl_m_group
        SET f_modulAccess = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', COALESCE(f_modulAccess,''), ','), CONCAT(',', :mid_csv, ','), ',')),
            f_updatedt = NOW()
        WHERE FIND_IN_SET(:mid_find, f_modulAccess)
    ");
    $cleanGroups->execute([
        ':mid_csv' => $moduleID,
        ':mid_find' => $moduleID,
    ]);

    $delStmt = $pdo->prepare("DELETE FROM tbl_m_modul WHERE f_modulID = :mid LIMIT 1");
    $delStmt->execute([':mid' => $moduleID]);
    if ($delStmt->rowCount() <= 0) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => __('userGroup_delete_module_fail')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $remaining = $pdo->query("SELECT f_modulID FROM tbl_m_modul ORDER BY COALESCE(f_order, 99999), f_modulID FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $orderStmt = $pdo->prepare("UPDATE tbl_m_modul SET f_order = :ord WHERE f_modulID = :mid");
    $ord = 1;
    foreach ($remaining as $remainingId) {
        $orderStmt->execute([
            ':ord' => $ord,
            ':mid' => (int)$remainingId,
        ]);
        $ord++;
    }

    $pdo->commit();

    try {
        if (function_exists('audit_event')) {
            $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
            $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
            $actorLabel = function_exists('audit_format_actor_label')
                ? audit_format_actor_label($nama, $nostaf)
                : ($nama ?: 'System User');
            $message = function_exists('audit_format_message')
                ? audit_format_message('Module deleted', $actorLabel)
                : ('Module deleted by ' . $actorLabel);

            audit_event([
                'event_type' => 'DELETE',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'module',
                'target_id' => (string)$moduleID,
                'target_label' => (string)($module['nama'] ?? ('Modul ' . $moduleID)),
                'message' => $message,
                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                'session_id' => session_id() ?: null,
                'user_id' => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja'])
                    ? (int)$_SESSION['f_nopekerja']
                    : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
                'actor_label' => $actorLabel,
                'meta' => [
                    'groups_cleaned' => (int)$cleanGroups->rowCount(),
                    'remaining_modules' => count($remaining),
                ],
            ]);
        }
    } catch (Throwable $e) {
        error_log('[module-delete] Audit logging failed: ' . $e->getMessage());
    }

    GroupDataCache::clear('menu_list_');
    GroupDataCache::clear('group_access_');
    GroupDataCache::clear('group_perms_');
    GroupDataCache::clear('modul_list_');
    clearSidebarNavigationCaches();

    echo json_encode([
        'error' => false,
        'message' => __('userGroup_delete_module_success'),
        'moduleID' => $moduleID,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('[module-delete] ' . $e->getMessage());
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-delete.php — kekal sokong groupID/hard/cascade, tapi tetap clean+delete global
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true) ?: [];
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrfHeader = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $csrfBody = (string)($body['csrf_token'] ?? '');
    $csrf = $csrfHeader !== '' ? $csrfHeader : $csrfBody;
    if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
        http_response_code(400);
        echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE); exit;
    }
    
    // Rate limiting: max 10 requests per 60 seconds (critical operation)
    if (!checkRateLimit('menu_delete', 10, 60)) {
        http_response_code(429);
        echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
    }
    
    // Permission check
    $db = Database::getInstance()->getConnection();
    if (!hasGroupManagePermission($db)) {
        http_response_code(403);
        echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_menu_delete_permission_denied')], JSON_UNESCAPED_UNICODE); exit;
    }

    $menuID    = (int)($body['menuID'] ?? 0);
    $groupID   = (int)($body['groupID'] ?? 0); // optional, utk reporting sahaja
    if ($menuID <= 0) {
        http_response_code(422);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_menu_invalid_id')], JSON_UNESCAPED_UNICODE); exit;
    }

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // Get menu data sebelum delete untuk audit
    $menuStmt = $db->prepare("SELECT f_menuID, f_modulID, f_path, f_menuName_ms, f_menuName_en, f_flag, f_order FROM tbl_m_menu WHERE f_menuID = :mid LIMIT 1");
    $menuStmt->execute([':mid' => $menuID]);
    $menuData = $menuStmt->fetch(PDO::FETCH_ASSOC);
    if (!$menuData) {
        $db->rollBack();
        echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_menu_not_found')], JSON_UNESCAPED_UNICODE); exit;
    }

    // (Opsyenal) tandakan kalau kumpulan konteks berubah
    $removedFromGroup = false;
    if ($groupID > 0) {
        $stmt = $db->prepare("SELECT COALESCE(f_menuAccess,'') FROM tbl_m_group WHERE f_groupID = :gid FOR UPDATE");
        $stmt->execute([':gid'=>$groupID]);
        $csv = (string)($stmt->fetchColumn() ?? '');
        if ($csv !== '') {
            $sql1 = "
                UPDATE tbl_m_group
                SET f_menuAccess = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', COALESCE(f_menuAccess,''), ','),
                                         CONCAT(',', :mid, ','), ',')),
                    f_updatedt = NOW()
                WHERE f_groupID = :gid
            ";
            $u = $db->prepare($sql1);
            $u->execute([':mid'=>$menuID, ':gid'=>$groupID]);
            $removedFromGroup = ($u->rowCount() > 0 && $csv !== '');
        }
    }

    // Buang menuID ini daripada SEMUA kumpulan
    $sqlCleanAll = "
        UPDATE tbl_m_group
        SET f_menuAccess = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', COALESCE(f_menuAccess,''), ','),
                                 CONCAT(',', :mid_val, ','), ',')),
            f_updatedt = NOW()
        WHERE FIND_IN_SET(:mid_match, f_menuAccess)
    ";
    $stCleanAll = $db->prepare($sqlCleanAll);
    $stCleanAll->execute([':mid_val' => $menuID, ':mid_match' => $menuID]);
    $groupsCleaned = $stCleanAll->rowCount();

    // Padam menu dari jadual menu
    $stDel = $db->prepare("DELETE FROM tbl_m_menu WHERE f_menuID = :mid");
    $stDel->execute([':mid' => $menuID]);
    if ($stDel->rowCount() === 0) {
        $db->rollBack();
        echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_menu_not_found')], JSON_UNESCAPED_UNICODE); exit;
    }

    $db->commit();
    
    // Audit: Log menu deletion
    try {
        if (function_exists('audit_event')) {
            $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
            
            // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
            $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
            $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
            $actorLabel = null;
            if (function_exists('audit_format_actor_label')) {
                $actorLabel = audit_format_actor_label($nama, $nostaf);
            } else {
                // Fallback: guna nama sahaja jika helper tidak available
                $actorLabel = $nama;
            }
            
            // ✅ FIX: Message dalam bahasa Inggeris dengan format: "[action] by [actor_label]"
            $message = audit_format_message('Menu deleted and removed from all groups', $actorLabel);
            
            audit_event([
                'event_type'  => 'DELETE',
                'severity'    => 'WARN',
                'outcome'     => 'SUCCESS',
                'target_type' => 'menu',
                'target_id'   => (string)$menuID,
                'target_label' => 'Menu: ' . ($menuData['f_menuName_ms'] ?? 'Unknown'),
                'message'     => $message,
                'request_id'  => $requestId,
                'session_id'  => session_id() ?: null,
                'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
                'actor_label' => $actorLabel,
                'meta'        => [
                    'groups_affected' => $groupsCleaned,
                    'path' => $menuData['f_path'] ?? null,
                    'modulID' => $menuData['f_modulID'] ?? null,
                    'removed_from_group' => $removedFromGroup
                ]
            ]);
        }
    } catch (\Throwable $e) {
        error_log('[menu-delete] Audit logging failed: ' . $e->getMessage());
    }
    
    // Clear cache selepas delete menu
    GroupDataCache::clear('menu_list_');
    GroupDataCache::clear('group_perms_');
    GroupDataCache::clear('group_access_');
    clearSidebarNavigationCaches();
    
    echo json_encode([
        'error'              => false,
        'deleted_menu'       => true,
        'removed_from_group' => $removedFromGroup,
        'groups_cleaned'     => (int)$groupsCleaned
    ]);
} catch (Throwable $e) {
    if (!empty($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('[menu-delete] ' . $e->getMessage());
    echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

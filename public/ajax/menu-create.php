<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-create.php
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
    // ===== CSRF =====
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrf === '') {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'x-csrf-token') {
                $csrf = (string)$value;
                break;
            }
        }
    }
    if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(400);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE); exit;
    }
    
    // Rate limiting: max 20 requests per 60 seconds (write operation)
    if (!checkRateLimit('menu_create', 20, 60)) {
        http_response_code(429);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
    }
    
    // Permission check
    $db = Database::getInstance('mysql')->getConnection();
    if (!hasGroupManagePermission($db)) {
        http_response_code(403);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_menu_create_permission_denied')], JSON_UNESCAPED_UNICODE); exit;
    }

    // ===== Input =====
    $data    = json_decode(file_get_contents('php://input'), true) ?: [];
    $groupID = (int)($data['groupID'] ?? 0);
    $modulID = (int)($data['modulID'] ?? 0);
    $subgroupID = (int)($data['subgroupID'] ?? $data['f_subgroupID'] ?? 0);
    $path    = trim((string)($data['path'] ?? ''));
    $name_ms = (string)($data['name_ms'] ?? '');
    $name_en = (string)($data['name_en'] ?? '');
    $domain  = strtoupper(trim((string)($data['domain'] ?? 'SHARED')));
    $showStaffOnly = isset($data['show_staff_only']) ? (int)$data['show_staff_only'] : 1;
    // zh/ta removed from schema — ignore incoming values
    $name_zh = '';
    $name_ta = '';
    $flag    = (int)($data['flag'] ?? 1);
    $allowedDomains = ['STAF', 'PELAJAR', 'UMUM', 'SHARED'];
    if (!in_array($domain, $allowedDomains, true)) {
        $domain = 'SHARED';
    }
    $showStaffOnly = $showStaffOnly === 1 ? 1 : 0;

    if ($groupID <= 0 || $modulID <= 0 || $path === '') {
        http_response_code(422);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_group_modul_path_required')], JSON_UNESCAPED_UNICODE); exit;
    }

    $db = Database::getInstance('mysql')->getConnection();
    $db->beginTransaction();

    // (Pilihan) Semak kewujudan kumpulan & modul (untuk error yang lebih jelas)
    // Kumpulan (lock supaya update CSV konsisten)
    $stmt = $db->prepare("SELECT f_groupID, COALESCE(f_menuAccess,'') AS f_menuAccess FROM tbl_m_group WHERE f_groupID = :gid FOR UPDATE");
    $stmt->execute([':gid'=>$groupID]);
    $grp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grp) {
        http_response_code(404);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_not_found')], JSON_UNESCAPED_UNICODE); 
        $db->rollBack(); exit;
    }

    // Modul (sekadar wujud)
    $stmt = $db->prepare("SELECT f_modulID FROM tbl_m_modul WHERE f_modulID = :mid");
    $stmt->execute([':mid'=>$modulID]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_target_module_not_found')], JSON_UNESCAPED_UNICODE);
        $db->rollBack(); exit;
    }

    if ($subgroupID > 0) {
        $stmt = $db->prepare("SELECT f_subgroupID FROM tbl_m_menu_subgroup WHERE f_subgroupID = :sid AND f_modulID = :mid AND f_status = 1 LIMIT 1");
        $stmt->execute([':sid' => $subgroupID, ':mid' => $modulID]);
        if (!$stmt->fetchColumn()) {
            http_response_code(422);
            echo json_encode(['error'=>true,'message'=>(string)__('userGroup_subgroup_not_same_module')], JSON_UNESCAPED_UNICODE);
            $db->rollBack(); exit;
        }
    }

    // Dapatkan f_order seterusnya dalam modul tersebut
    $stmt = $db->prepare("SELECT COALESCE(MAX(f_order),0)+1 AS nextOrd FROM tbl_m_menu WHERE f_modulID = :mid");
    $stmt->execute([':mid'=>$modulID]);
    $nextOrd = (int)($stmt->fetchColumn() ?: 1);

    // CIPTA MENU
    $stmt = $db->prepare(
        "INSERT INTO tbl_m_menu (f_modulID, f_subgroupID, f_path, f_domain, f_show_staff_only, f_menuName_ms, f_menuName_en, f_flag, f_order, f_insertdt, f_updatedt, f_updateby) VALUES (:mid, :subgroupID, :path, :domain, :showStaffOnly, :ms, :en, :flag, :ord, NOW(), NOW(), :updateby)"
    );
    $stmt->execute([
        ':mid'  => $modulID,
        ':subgroupID' => $subgroupID > 0 ? $subgroupID : null,
        ':path' => $path,
        ':domain' => $domain,
        ':showStaffOnly' => $showStaffOnly,
        ':ms'   => $name_ms,
        ':en'   => $name_en,
        ':flag' => $flag,
        ':ord'  => $nextOrd,
        ':updateby' => (string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? ''),
    ]);
    $menuID = (int)$db->lastInsertId();

    // LINK MENU → KUMPULAN melalui CSV f_menuAccess (tiada join-table dalam skema semasa)
    $currCsv = (string)($grp['f_menuAccess'] ?? '');
    $ids = array_values(array_filter(
        array_map('intval', preg_split('/\s*,\s*/', $currCsv, -1, PREG_SPLIT_NO_EMPTY)),
        fn($v) => $v > 0
    ));
    $ids[] = $menuID;

    // Deduplicate sambil kekal tertib sedia ada (menu baharu di hujung)
    $seen = [];
    $ids = array_values(array_filter($ids, function ($v) use (&$seen) {
        if (isset($seen[$v])) return false;
        $seen[$v] = true; return true;
    }));
    $newCsv = implode(',', $ids);

    // Simpan CSV + cap masa kemaskini (kolum f_updatedt wujud)
    $stmt = $db->prepare("UPDATE tbl_m_group SET f_menuAccess = :csv, f_updatedt = NOW() WHERE f_groupID = :gid");
    $stmt->execute([':csv'=>$newCsv, ':gid'=>$groupID]);

    $db->commit();
    
    // Audit: Log menu creation
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
            $message = audit_format_message('Menu created and assigned to group', $actorLabel);
            
            $eventId = audit_event([
                'event_type'  => 'CREATE',
                'severity'    => 'INFO',
                'outcome'     => 'SUCCESS',
                'target_type' => 'menu',
                'target_id'   => (string)$menuID,
                'target_label' => 'Menu: ' . $name_ms,
                'message'     => $message,
                'request_id'  => $requestId,
                'session_id'  => session_id() ?: null,
                'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
                'actor_label' => $actorLabel,
                'meta'        => [
                    'modulID' => $modulID,
                    'subgroupID' => $subgroupID,
                    'groupID' => $groupID,
                    'path' => $path,
                    'flag' => $flag
                ]
            ]);

            if ($eventId) {
                $changeSetId = audit_begin_change($eventId, 'menu', (string)$menuID, 'Menu creation');
                if ($changeSetId) {
                    audit_change($changeSetId, 'f_path', null, $path, 'string', false);
                    audit_change($changeSetId, 'f_subgroupID', null, $subgroupID > 0 ? (string)$subgroupID : '', 'integer', false);
                    audit_change($changeSetId, 'f_menuName_ms', null, $name_ms, 'string', false);
                    audit_change($changeSetId, 'f_menuName_en', null, $name_en, 'string', false);
                    audit_change($changeSetId, 'f_domain', null, $domain, 'string', false);
                    audit_change($changeSetId, 'f_show_staff_only', null, (string)$showStaffOnly, 'integer', false);
                    // zh/ta removed from schema — omitted
                    audit_change($changeSetId, 'f_flag', null, (string)$flag, 'integer', false);
                    audit_change($changeSetId, 'f_order', null, (string)$nextOrd, 'integer', false);
                    audit_change($changeSetId, 'f_groupAccess', null, $newCsv, 'string', false);
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[menu-create] Audit logging failed: ' . $e->getMessage());
    }
    
    // Clear cache selepas create menu
    GroupDataCache::clear('menu_list_');
    GroupDataCache::clear('group_perms_');
    GroupDataCache::clear('group_access_');
    clearSidebarNavigationCaches();

    echo json_encode([
        'error' => false,
        'menu'  => [
            'f_menuID'       => $menuID,
            'f_modulID'      => $modulID,
            'f_subgroupID'   => $subgroupID,
            'f_path'         => $path,
            'f_menuName_ms'  => $name_ms,
            'f_menuName_en'  => $name_en,
            'f_domain'       => $domain,
            'f_show_staff_only' => $showStaffOnly,
            'f_flag'         => $flag,
            'f_order'        => $nextOrd,
        ],
        // pulangkan CSV terkini (kalau UI nak refresh cepat)
        'group' => [
            'f_groupID'      => $groupID,
            'f_menuAccess'   => $newCsv,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    error_log('[menu-create] ' . $e->getMessage());
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

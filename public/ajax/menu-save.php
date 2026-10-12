<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-save.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
  exit;
}

// CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => __('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE); exit;
}

// Rate limiting: max 20 requests per 60 seconds (write operation)
if (!checkRateLimit('menu_save', 20, 60)) {
  http_response_code(429);
  echo json_encode(['error' => true, 'message' => __('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
}

// Permission check
$pdo = Database::getInstance('mysql')->getConnection();
if (!hasGroupManagePermission($pdo)) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => __('userGroup_menu_update_permission_denied')], JSON_UNESCAPED_UNICODE); exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$menuID   = (int)($payload['menuID'] ?? 0);
$modulID  = (int)($payload['modulID'] ?? 0);     // parent modul baharu
$subgroupID = (int)($payload['subgroupID'] ?? $payload['f_subgroupID'] ?? 0);
$path     = trim((string)($payload['path'] ?? ''));
$flag     = (int)($payload['flag'] ?? 0);        // 0/1
$name_ms  = trim((string)($payload['name_ms'] ?? ''));
$name_en  = trim((string)($payload['name_en'] ?? ''));
$domain   = strtoupper(trim((string)($payload['domain'] ?? 'SHARED')));
$showStaffOnly = isset($payload['show_staff_only']) ? (int)$payload['show_staff_only'] : 1;
// optional: 'top'|'bottom' (default bottom)
$position = $payload['position'] ?? 'bottom';
$allowedDomains = ['STAF', 'PELAJAR', 'UMUM', 'SHARED'];
if (!in_array($domain, $allowedDomains, true)) {
  $domain = 'SHARED';
}
$showStaffOnly = $showStaffOnly === 1 ? 1 : 0;

try {
  if ($menuID <= 0 || $modulID <= 0 || $path === '') throw new Exception((string)__('userGroup_invalid_payload'));

  $pdo = Database::getInstance('mysql')->getConnection();
  $pdo->beginTransaction();

  // Wujud menu + modul - Get old data untuk audit
  $curStmt = $pdo->prepare("SELECT f_menuID, f_modulID, COALESCE(f_subgroupID,0) AS f_subgroupID, f_order, f_path, f_flag, f_menuName_ms, f_menuName_en, COALESCE(f_domain,'SHARED') AS f_domain, COALESCE(f_show_staff_only,1) AS f_show_staff_only FROM tbl_m_menu WHERE f_menuID = ? FOR UPDATE");
  $curStmt->execute([$menuID]);
  $cur = $curStmt->fetch(PDO::FETCH_ASSOC);
  if (!$cur) throw new Exception((string)__('userGroup_menu_not_found'));
  
  // Store old values untuk audit
  $oldData = [
    'f_modulID' => (int)$cur['f_modulID'],
    'f_subgroupID' => (int)($cur['f_subgroupID'] ?? 0),
    'f_path' => (string)$cur['f_path'],
    'f_flag' => (int)$cur['f_flag'],
    'f_order' => (int)($cur['f_order'] ?? 0),
    'f_menuName_ms' => (string)$cur['f_menuName_ms'],
    'f_menuName_en' => (string)$cur['f_menuName_en'],
    'f_domain' => (string)($cur['f_domain'] ?? 'SHARED'),
    'f_show_staff_only' => (int)($cur['f_show_staff_only'] ?? 1),
  ];

  $modStmt = $pdo->prepare("SELECT f_modulID FROM tbl_m_modul WHERE f_modulID = ? LIMIT 1");
  $modStmt->execute([$modulID]);
  if (!$modStmt->fetch(PDO::FETCH_ASSOC)) throw new Exception((string)__('userGroup_target_module_not_found'));

  if ($subgroupID > 0) {
    $subgroupStmt = $pdo->prepare("SELECT f_subgroupID FROM tbl_m_menu_subgroup WHERE f_subgroupID = ? AND f_modulID = ? AND f_status = 1 LIMIT 1");
    $subgroupStmt->execute([$subgroupID, $modulID]);
    if (!$subgroupStmt->fetchColumn()) throw new Exception((string)__('userGroup_subgroup_not_same_module'));
  }

  $oldModul = (int)$cur['f_modulID'];
  $oldOrder = (int)($cur['f_order'] ?? 0);

  // Path unik dalam modul sasaran
  $dupe = $pdo->prepare("SELECT f_menuID FROM tbl_m_menu WHERE f_modulID = ? AND f_path = ? AND f_menuID <> ? LIMIT 1");
  $dupe->execute([$modulID, $path, $menuID]);
  if ($dupe->fetch()) throw new Exception((string)__('userGroup_menu_path_duplicate'));

  $newOrder = $oldOrder;

  // Jika pindah modul → kemas order modul lama & kira order modul baru
  if ($modulID !== $oldModul) {
    if ($oldOrder > 0) {
      $pdo->prepare("UPDATE tbl_m_menu SET f_order = f_order - 1 WHERE f_modulID = ? AND f_order > ?")
          ->execute([$oldModul, $oldOrder]);
    }
    if ($position === 'top') {
      $pdo->prepare("UPDATE tbl_m_menu SET f_order = f_order + 1 WHERE f_modulID = ?")->execute([$modulID]);
      $newOrder = 1;
    } else {
      $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(f_order),0) FROM tbl_m_menu WHERE f_modulID = ?");
      $maxStmt->execute([$modulID]);
      $newOrder = ((int)$maxStmt->fetchColumn()) + 1;
    }
  }

  // Update butiran + parent
  $upd = $pdo->prepare("
    UPDATE tbl_m_menu
    SET f_modulID = :mid,
        f_subgroupID = :subgroupID,
        f_path    = :path,
        f_domain  = :domain,
        f_show_staff_only = :showstaffonly,
        f_flag    = :flag,
        f_order   = :ord,
        f_menuName_ms = :nms,
        f_menuName_en = :nen,
        f_updatedt = NOW(),
        f_updateby = :updateby
    WHERE f_menuID = :id
  ");
  $upd->execute([
    ':mid'=>$modulID, ':subgroupID'=>($subgroupID > 0 ? $subgroupID : null), ':path'=>$path, ':domain'=>$domain, ':showstaffonly'=>$showStaffOnly, ':flag'=>($flag?1:0), ':ord'=>$newOrder,
        ':nms'=>$name_ms, ':nen'=>$name_en,
    ':updateby'=>(string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? ''),
    ':id'=>$menuID
  ]);

  // Normalize dua modul (lama & baru) → order 1..N stabil
  $targets = array_unique([$oldModul, $modulID]);
  $re = $pdo->prepare("SELECT f_menuID FROM tbl_m_menu WHERE f_modulID = ? ORDER BY COALESCE(f_order, 99999), f_menuID");
  $up = $pdo->prepare("UPDATE tbl_m_menu SET f_order = :o WHERE f_menuID = :id AND f_modulID = :mid");
  foreach ($targets as $mid) {
    $re->execute([$mid]);
    $ids = $re->fetchAll(PDO::FETCH_COLUMN, 0);
    $pos = 1;
    foreach ($ids as $id) $up->execute([':o'=>$pos++, ':id'=>(int)$id, ':mid'=>$mid]);
  }

  $pdo->commit();
  
  // Audit: Log menu update dengan field changes
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
      $message = audit_format_message('Menu updated', $actorLabel);
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
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
          'old_modulID' => $oldData['f_modulID'],
          'new_modulID' => $modulID,
          'moved_module' => ($oldData['f_modulID'] !== $modulID)
        ]
      ]);

      if ($eventId) {
        $changeSetId = audit_begin_change($eventId, 'menu', (string)$menuID, 'Menu update');
        if ($changeSetId) {
          if ($oldData['f_modulID'] !== $modulID) {
            audit_change($changeSetId, 'f_modulID', (string)$oldData['f_modulID'], (string)$modulID, 'integer', false);
          }
          if ($oldData['f_subgroupID'] !== $subgroupID) {
            audit_change($changeSetId, 'f_subgroupID', (string)$oldData['f_subgroupID'], (string)$subgroupID, 'integer', false);
          }
          if ($oldData['f_path'] !== $path) {
            audit_change($changeSetId, 'f_path', $oldData['f_path'], $path, 'string', false);
          }
          if ($oldData['f_flag'] !== ($flag ? 1 : 0)) {
            audit_change($changeSetId, 'f_flag', (string)$oldData['f_flag'], (string)($flag ? 1 : 0), 'integer', false);
          }
          if ($oldData['f_domain'] !== $domain) {
            audit_change($changeSetId, 'f_domain', $oldData['f_domain'], $domain, 'string', false);
          }
          if ($oldData['f_show_staff_only'] !== $showStaffOnly) {
            audit_change($changeSetId, 'f_show_staff_only', (string)$oldData['f_show_staff_only'], (string)$showStaffOnly, 'integer', false);
          }
          if ($oldData['f_order'] !== $newOrder) {
            audit_change($changeSetId, 'f_order', (string)$oldData['f_order'], (string)$newOrder, 'integer', false);
          }
          if ($oldData['f_menuName_ms'] !== $name_ms) {
            audit_change($changeSetId, 'f_menuName_ms', $oldData['f_menuName_ms'], $name_ms, 'string', false);
          }
          if ($oldData['f_menuName_en'] !== $name_en) {
            audit_change($changeSetId, 'f_menuName_en', $oldData['f_menuName_en'], $name_en, 'string', false);
          }
          // zh/ta removed from schema — no changes logged for those fields
        }
      }
    }
  } catch (\Throwable $e) {
    error_log('[menu-save] Audit logging failed: ' . $e->getMessage());
  }
  
  // Clear cache selepas save menu
  require_once __DIR__ . '/_helpers.php';
  GroupDataCache::clear('menu_list_');
  GroupDataCache::clear('group_perms_');
  GroupDataCache::clear('group_access_');
  clearSidebarNavigationCaches();
  
  echo json_encode(['ok'=>true, 'message'=>(string)__('userGroup_menu_save_success_update'), 'menuID'=>$menuID, 'modulID'=>$modulID, 'subgroupID'=>$subgroupID, 'order'=>$newOrder], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  error_log('[menu-save] ' . $e->getMessage());
  echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

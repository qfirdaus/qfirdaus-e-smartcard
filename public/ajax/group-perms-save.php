<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/group-perms-save.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
  exit;
}

try{
  $rawBody = file_get_contents('php://input');
  $json = json_decode($rawBody, true) ?: [];
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $csrfHdr = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  $csrfBody = (string)($json['csrf_token'] ?? '');
  $csrfToken = $csrfHdr !== '' ? $csrfHdr : $csrfBody;
  if ($csrfToken === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(400);
    echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE); exit;
  }
  
  // Rate limiting: max 20 requests per 60 seconds (write operation)
  if (!checkRateLimit('group_perms_save', 20, 60)) {
    http_response_code(429);
    echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
  }
  
  // Permission check
  $db = Database::getInstance('mysql')->getConnection();
  if (!hasGroupManagePermission($db)) {
    http_response_code(403);
    echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_group_permissions_not_allowed')], JSON_UNESCAPED_UNICODE); exit;
  }

  $gid  = (int)($json['groupID'] ?? 0);
  $mods = $json['modulIDs'] ?? [];
  $menus= $json['menuIDs'] ?? [];
  if ($gid<=0) { http_response_code(422); echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_invalid_id')], JSON_UNESCAPED_UNICODE); exit; }

  $norm = function($arr){
    $out=[]; foreach ((array)$arr as $v) { if ($v !== '' && is_numeric($v)) $out[] = (int)$v; }
    $out = array_values(array_unique($out)); sort($out, SORT_NUMERIC); return implode(',', $out);
  };

  // Get old permissions sebelum update untuk audit
  $oldStmt = $db->prepare("SELECT f_modulAccess, f_menuAccess, f_groupKod, f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
  $oldStmt->execute([':gid' => $gid]);
  $oldPerms = $oldStmt->fetch(PDO::FETCH_ASSOC);
  if (!$oldPerms) {
    http_response_code(404);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_not_found')], JSON_UNESCAPED_UNICODE); exit;
  }

  $newMods = $norm($mods);
  $newMenus = $norm($menus);
  $oldMods = (string)($oldPerms['f_modulAccess'] ?? '');
  $oldMenus = (string)($oldPerms['f_menuAccess'] ?? '');

  $db = Database::getInstance('mysql')->getConnection();
  $upd= $db->prepare("UPDATE tbl_m_group SET f_modulAccess=:ma, f_menuAccess=:me WHERE f_groupID=:g");
  $upd->execute([':ma'=>$newMods, ':me'=>$newMenus, ':g'=>$gid]);
  
  // Audit: Log group permissions update
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
      $message = audit_format_message('Group module and menu access updated', $actorLabel);
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => 'group',
        'target_id'   => (string)$gid,
        'target_label' => 'Group: ' . ($oldPerms['f_groupName'] ?? 'Unknown'),
        'message'     => $message,
        'request_id'  => $requestId,
        'session_id'  => session_id() ?: null,
        'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
        'actor_label' => $actorLabel,
        'meta'        => [
          'groupID' => $gid,
          'groupName' => $oldPerms['f_groupName'] ?? null
        ]
      ]);

      if ($eventId) {
        $changeSetId = audit_begin_change($eventId, 'group', (string)$gid, 'Group permissions update');
        if ($changeSetId) {
          if ($oldMods !== $newMods) {
            audit_change($changeSetId, 'f_modulAccess', $oldMods, $newMods, 'string', false);
          }
          if ($oldMenus !== $newMenus) {
            audit_change($changeSetId, 'f_menuAccess', $oldMenus, $newMenus, 'string', false);
          }
        }
      }
    }
  } catch (\Throwable $e) {
    error_log('[group-perms-save] Audit logging failed: ' . $e->getMessage());
  }
  
  // Clear cache selepas save permissions (including user list group cache/style map source)
  clearGroupUiCaches($gid);
  clearSidebarNavigationCaches();

  $userCountStmt = $db->prepare('SELECT COUNT(*) FROM tbl_m_user WHERE f_groupID = :gid');
  $userCountStmt->execute([':gid' => $gid]);
  $userCount = (int)$userCountStmt->fetchColumn();
  $groupKod = (string)($oldPerms['f_groupKod'] ?? '');
  $saId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
  $saCode = defined('PRESTASI_ROLE_KOD_ADM_SA')
    ? strtoupper(trim((string)PRESTASI_ROLE_KOD_ADM_SA))
    : (defined('PRESTASI_ROLE_ADM_SA') ? strtoupper(trim((string)PRESTASI_ROLE_ADM_SA)) : 'ADM-SA');
  $isProtectedGroup = ($saId > 0 && $gid === $saId)
    || (strtoupper(trim($groupKod)) === $saCode);

  echo json_encode([
    'error' => false,
    'ok' => true,
    'group' => [
      'id' => $gid,
      'kod' => $groupKod,
      'nama' => (string)($oldPerms['f_groupName'] ?? ''),
      'modulAccess' => $newMods === '' ? [] : explode(',', $newMods),
      'menuAccess' => $newMenus === '' ? [] : explode(',', $newMenus),
      'userCount' => $userCount,
      'canDelete' => $newMods === '' && $newMenus === '' && $userCount === 0 && !$isProtectedGroup,
    ],
  ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  http_response_code(500);
  error_log('[group-perms-save] ' . $e->getMessage());
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

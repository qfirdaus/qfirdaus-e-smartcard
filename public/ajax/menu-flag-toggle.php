<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ======================================
// ✅ AJAX: Toggle Menu Flag (ON/OFF)
// ======================================
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

// CSRF simple
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => __('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
  exit;
}

// Rate limiting: max 30 requests per 60 seconds
if (!checkRateLimit('menu_flag_toggle', 30, 60)) {
  http_response_code(429);
  echo json_encode(['error' => true, 'message' => __('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
  exit;
}

// Permission check
$pdo = Database::getInstance('mysql')->getConnection();
if (!hasGroupManagePermission($pdo)) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => __('userGroup_menu_status_permission_denied')], JSON_UNESCAPED_UNICODE);
  exit;
}

// Baca JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$menuID = isset($data['menuID']) ? (int)$data['menuID'] : 0;
$flag   = isset($data['flag']) ? (int)$data['flag'] : 0;
$flag   = ($flag === 1) ? 1 : 0;

if ($menuID <= 0) {
  http_response_code(400);
  echo json_encode(['error' => true, 'message' => __('userGroup_menu_invalid_id')], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // ✅ Guna Database class standard projek
  $pdo = Database::getInstance('mysql')->getConnection();

  // Get old flag value untuk audit
  $oldStmt = $pdo->prepare("SELECT f_flag, f_menuName_ms, f_path FROM tbl_m_menu WHERE f_menuID = :id LIMIT 1");
  $oldStmt->execute([':id' => $menuID]);
  $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
  if (!$oldData) {
    http_response_code(404);
    echo json_encode(['error' => true, 'message' => __('userGroup_menu_not_found')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $oldFlag = (int)($oldData['f_flag'] ?? 0);

  $stmt = $pdo->prepare("UPDATE tbl_m_menu SET f_flag = :flag WHERE f_menuID = :id");
  $stmt->execute([':flag' => $flag, ':id' => $menuID]);
  
  // Audit: Log menu flag toggle
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
      $message = audit_format_message('Menu flag toggled', $actorLabel);
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => 'menu',
        'target_id'   => (string)$menuID,
        'target_label' => 'Menu: ' . ($oldData['f_menuName_ms'] ?? 'Unknown'),
        'message'     => $message,
        'request_id'  => $requestId,
        'session_id'  => session_id() ?: null,
        'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
        'actor_label' => $actorLabel,
        'meta'        => [
          'path' => $oldData['f_path'] ?? null,
          'action' => ($flag === 1 ? 'enabled' : 'disabled')
        ]
      ]);

      if ($eventId) {
        $changeSetId = audit_begin_change($eventId, 'menu', (string)$menuID, 'Menu flag toggle');
        if ($changeSetId && $oldFlag !== $flag) {
          audit_change($changeSetId, 'f_flag', (string)$oldFlag, (string)$flag, 'integer', false);
        }
      }
    }
  } catch (\Throwable $e) {
    error_log('[menu-flag-toggle] Audit logging failed: ' . $e->getMessage());
  }
  
  // Clear cache selepas toggle flag
  GroupDataCache::clear('menu_list_');
  GroupDataCache::clear('group_access_');
  clearSidebarNavigationCaches();

  echo json_encode(['ok' => true, 'menuID' => $menuID, 'flag' => $flag], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  error_log('[menu-flag-toggle] ' . $e->getMessage());
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

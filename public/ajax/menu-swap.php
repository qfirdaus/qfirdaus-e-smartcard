<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-swap.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

function bad($msg, $code=400){ http_response_code($code); echo json_encode(['error'=>true,'message'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') bad((string)__('userGroup_method_not_allowed'), 405);
  if (!isValidCsrfToken()) bad((string)__('userGroup_csrf_invalid'), 403);
  
  // Rate limiting: max 30 requests per 60 seconds
  if (!checkRateLimit('menu_swap', 30, 60)) {
    bad((string)__('userGroup_rate_limit_text'), 429);
  }
  
  // Permission check
  $pdo = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($pdo, (string)__('userGroup_group_permissions_not_allowed'));

  $in = json_decode(file_get_contents('php://input') ?: '[]', true);
  $aID = isset($in['aID']) ? (int)$in['aID'] : 0;
  $bID = isset($in['bID']) ? (int)$in['bID'] : 0;
  if ($aID<=0 || $bID<=0) bad((string)__('userGroup_invalid_payload'));
  $pdo->beginTransaction();

  // Ambil dua rekod & lock
  $st = $pdo->prepare("SELECT f_menuID, f_modulID, f_order FROM tbl_m_menu WHERE f_menuID IN (?, ?) FOR UPDATE");
  $st->execute([$aID, $bID]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  if (count($rows) !== 2) { $pdo->rollBack(); bad((string)__('userGroup_menu_not_found'), 404); }

  // Pastikan kedua-dua dalam modul yang sama (guna data DB, abaikan modulID dari client)
  $modA = (int)$rows[0]['f_modulID'];
  $modB = (int)$rows[1]['f_modulID'];
  if ($modA !== $modB) { $pdo->rollBack(); bad((string)__('userGroup_menu_not_same_module')); }
  $modulID = $modA;

  // Pastikan tiada NULL/duplicate: renumber 10,20,30...
  $needRenumber = false;
  foreach ($rows as $r) { if ($r['f_order'] === null) { $needRenumber = true; break; } }
  if (!$needRenumber) {
    $chk = $pdo->prepare("SELECT COUNT(*) c, COUNT(DISTINCT f_order) d FROM tbl_m_menu WHERE f_modulID=?");
    $chk->execute([$modulID]);
    $agg = $chk->fetch(PDO::FETCH_ASSOC);
    if ($agg && (int)$agg['c'] !== (int)$agg['d']) $needRenumber = true;
  }

  if ($needRenumber) {
    $q = $pdo->prepare("SELECT f_menuID FROM tbl_m_menu WHERE f_modulID=? ORDER BY COALESCE(f_order, 999999), f_menuID");
    $q->execute([$modulID]);
    $ids = $q->fetchAll(PDO::FETCH_COLUMN, 0);
    $ord = 10;
    $u = $pdo->prepare("UPDATE tbl_m_menu SET f_order=? WHERE f_menuID=?");
    foreach ($ids as $id) { $u->execute([$ord, (int)$id]); $ord += 10; }

    // Refresh order A/B
    $st->execute([$aID, $bID]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

  // Swap
  $oa = null; $ob = null;
  $menuA = null; $menuB = null;
  foreach ($rows as $r) {
    if ((int)$r['f_menuID'] === $aID) {
      $oa = (int)$r['f_order'];
      $menuA = $r;
    }
    if ((int)$r['f_menuID'] === $bID) {
      $ob = (int)$r['f_order'];
      $menuB = $r;
    }
  }
  if ($oa === null || $ob === null) { $pdo->rollBack(); bad((string)__('userGroup_menu_read_order_error')); }

  $u = $pdo->prepare("UPDATE tbl_m_menu SET f_order=? WHERE f_menuID=?");
  $u->execute([$ob, $aID]);
  $u->execute([$oa, $bID]);

  $pdo->commit();
  
  // Audit: Log menu order swap
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
      $message = audit_format_message('Menu order swapped', $actorLabel);
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => 'menu',
        'target_id'   => (string)$aID . ',' . (string)$bID,
        'target_label' => 'Menu Order Swap',
        'message'     => $message,
        'request_id'  => $requestId,
        'session_id'  => session_id() ?: null,
        'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
        'actor_label' => $actorLabel,
        'meta'        => [
          'modulID' => $modulID,
          'menuA_id' => $aID,
          'menuB_id' => $bID,
          'old_order_A' => $oa,
          'old_order_B' => $ob,
          'new_order_A' => $ob,
          'new_order_B' => $oa
        ]
      ]);

      if ($eventId) {
        $changeSetId = audit_begin_change($eventId, 'menu', (string)$aID . ',' . (string)$bID, 'Menu order swap');
        if ($changeSetId) {
          audit_change($changeSetId, 'f_order_menu_' . $aID, (string)$oa, (string)$ob, 'integer', false);
          audit_change($changeSetId, 'f_order_menu_' . $bID, (string)$ob, (string)$oa, 'integer', false);
        }
      }
    }
  } catch (\Throwable $e) {
    error_log('[menu-swap] Audit logging failed: ' . $e->getMessage());
  }
  
  // Clear cache selepas swap menu
  require_once __DIR__ . '/_helpers.php';
  GroupDataCache::clear('menu_list_');
  GroupDataCache::clear('group_access_');
  clearSidebarNavigationCaches();
  
  echo json_encode(['error'=>false, 'message'=>(string)__('userGroup_ok'), 'modulID'=>$modulID, 'swapped'=>['a'=>$aID,'b'=>$bID]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  error_log('[menu-swap] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

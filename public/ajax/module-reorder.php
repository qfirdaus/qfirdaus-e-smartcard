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

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['error' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    bad((string)__('userGroup_method_not_allowed'), 405);
  }

  if (!isValidCsrfToken()) {
    bad((string)__('userGroup_csrf_invalid'), 403);
  }

  if (!checkRateLimit('module_reorder', 30, 60)) {
    bad((string)__('userGroup_rate_limit_text'), 429);
  }

  $pdo = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($pdo, (string)__('userGroup_module_reorder_not_allowed'));

  $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
  $ordered = $payload['orderedIDs'] ?? [];
  if (!is_array($ordered) || count($ordered) < 2) {
    bad((string)__('userGroup_module_reorder_invalid_payload'));
  }

  $submittedIds = [];
  foreach ($ordered as $id) {
    $v = (int)$id;
    if ($v > 0) $submittedIds[] = $v;
  }
  $submittedIds = array_values(array_unique($submittedIds));
  if (count($submittedIds) < 2) {
    bad((string)__('userGroup_module_reorder_minimum'));
  }

  $pdo->beginTransaction();

  $rows = $pdo->query("
    SELECT f_modulID, COALESCE(f_order, 999999) AS sort_order
    FROM tbl_m_modul
    ORDER BY COALESCE(f_order, 999999), f_modulID
    FOR UPDATE
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if (!$rows) {
    $pdo->rollBack();
    bad((string)__('userGroup_delete_module_not_found'), 404);
  }

  $currentIds = array_map(static fn(array $r): int => (int)$r['f_modulID'], $rows);
  $currentIdSet = array_fill_keys($currentIds, true);
  foreach ($submittedIds as $id) {
    if (!isset($currentIdSet[$id])) {
      $pdo->rollBack();
      bad((string)__('userGroup_delete_module_not_found'), 404);
    }
  }

  $positions = [];
  foreach ($currentIds as $idx => $id) {
    if (isset($currentIdSet[$id]) && in_array($id, $submittedIds, true)) {
      $positions[] = $idx;
    }
  }

  if (count($positions) !== count($submittedIds)) {
    $pdo->rollBack();
    bad((string)__('userGroup_module_reorder_incomplete'));
  }

  sort($positions);
  $newGlobalOrder = $currentIds;
  foreach ($positions as $i => $pos) {
    $newGlobalOrder[$pos] = $submittedIds[$i];
  }

  $update = $pdo->prepare("UPDATE tbl_m_modul SET f_order = :ord WHERE f_modulID = :id");
  $ord = 1;
  foreach ($newGlobalOrder as $moduleId) {
    $update->execute([
      ':ord' => $ord,
      ':id' => (int)$moduleId,
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
        ? audit_format_message('Module order updated', $actorLabel)
        : ('Module order updated by ' . $actorLabel);

      audit_event([
        'event_type' => 'UPDATE',
        'severity' => 'INFO',
        'outcome' => 'SUCCESS',
        'target_type' => 'module',
        'target_id' => implode(',', $submittedIds),
        'target_label' => 'Module Reorder',
        'message' => $message,
        'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
        'session_id' => session_id() ?: null,
        'user_id' => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja'])
          ? (int)$_SESSION['f_nopekerja']
          : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
        'actor_label' => $actorLabel,
        'meta' => [
          'submitted_ids' => $submittedIds,
          'global_order' => $newGlobalOrder,
        ],
      ]);
    }
  } catch (Throwable $e) {
    error_log('[module-reorder] Audit logging failed: ' . $e->getMessage());
  }

  GroupDataCache::clear('group_access_');
  GroupDataCache::clear('group_perms_');
  GroupDataCache::clear('modul_list_');
  clearSidebarNavigationCaches();

  echo json_encode([
    'error' => false,
    'message' => 'OK',
    'orderedIDs' => $submittedIds,
    'globalOrder' => $newGlobalOrder,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  error_log('[module-reorder] ' . $e->getMessage());
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

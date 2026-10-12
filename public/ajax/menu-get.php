<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-get.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
  exit;
}

$menuID = isset($_GET['menuID']) ? (int)$_GET['menuID'] : 0;
$langRaw = strtolower(trim((string)($_SESSION['lang'] ?? 'ms')));
$lang = in_array($langRaw, ['ms', 'en'], true) ? $langRaw : 'ms';

// Rate limiting: max 30 requests per 60 seconds (read operation)
if (!checkRateLimit('menu_get', 30, 60)) {
  http_response_code(429);
  echo json_encode(['error' => true, 'message' => __('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($menuID <= 0) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_menu_invalid_id')], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($pdo);

  // Butiran menu (nama ikut bahasa + semua varian untuk edit)
  $menuStmt = $pdo->prepare(
    "SELECT f_menuID, f_modulID, COALESCE(f_subgroupID, 0) AS f_subgroupID, f_path, f_flag, f_order,\n" .
    "       COALESCE(f_domain,'SHARED') AS f_domain,\n" .
    "       COALESCE(f_show_staff_only,1) AS f_show_staff_only,\n" .
    "       COALESCE(f_menuName_{$lang}, f_menuName_ms, f_menuName_en) AS menuName,\n" .
    "       f_menuName_ms, f_menuName_en\n" .
    "FROM tbl_m_menu\n" .
    "WHERE f_menuID = ?\n" .
    "LIMIT 1"
  );
  $menuStmt->execute([$menuID]);
  $menu = $menuStmt->fetch(PDO::FETCH_ASSOC);
  if (!$menu) {
    http_response_code(404);
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_menu_not_found')], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Senarai modul (nama ikut bahasa)
  $modStmt = $pdo->query("
    SELECT f_modulID,
           COALESCE(f_modulName_$lang, f_modulName_ms, f_modulName_en) AS modulName
    FROM tbl_m_modul
    ORDER BY COALESCE(f_order, 99999), f_modulID
  ");
  $moduls = $modStmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'menu'=>$menu, 'moduls'=>$moduls], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  error_log('[menu-get] ' . $e->getMessage());
  echo json_encode(['error'=>true, 'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

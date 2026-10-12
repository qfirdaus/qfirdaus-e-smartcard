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

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE); exit;
  }
  
  // Rate limiting: max 30 requests per 60 seconds (read operation)
  if (!checkRateLimit('menu_list', 30, 60)) {
    http_response_code(429);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
  }
  
  // Check cache (10 min TTL untuk menu list - lebih kerap berubah)
  $modulID = isset($_GET['modulID']) ? (int)$_GET['modulID'] : null;
  $all = isset($_GET['all']) ? (int)$_GET['all'] : 0;
  $active = isset($_GET['active']) ? (int)$_GET['active'] : null;
  $lang = preg_replace('/[^a-z_]/i', '', (string)($_SESSION['lang'] ?? 'ms')) ?: 'ms';
  $cacheKey = 'menu_list_' . ($modulID ?? 'all') . '_' . ($all ? 'all' : '') . '_' . ($active ?? 'any') . '_' . $lang;
  $cached = GroupDataCache::get($cacheKey, 600);
  if ($cached !== null) {
    header('X-Cache: HIT');
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = Database::pdoMysql();
  ensureAjaxGroupManagePermission($pdo);

  $modulID = isset($_GET['modulID']) ? (int)$_GET['modulID'] : null;
  $all     = isset($_GET['all']) ? (int)$_GET['all'] : 0;          // ?all=1 → semua modul
  $active  = isset($_GET['active']) ? (int)$_GET['active'] : null; // ?active=1 → hanya aktif

  $subgroupNameField = $lang === 'en' ? 'sg.f_subgroupName_en' : 'sg.f_subgroupName_ms';
  $sql = "SELECT
            m.f_menuID  AS id,
            m.f_modulID AS modulID,
            COALESCE(m.f_subgroupID, 0) AS subgroupID,
            COALESCE(NULLIF($subgroupNameField,''), NULLIF(sg.f_subgroupName_ms,''), NULLIF(sg.f_subgroupName_en,''), '') AS subgroupName,
            COALESCE(NULLIF(m.f_menuName_ms,''), NULLIF(m.f_menuName_en,''), m.f_path, CONCAT('Menu ', m.f_menuID)) AS nama,
            m.f_path    AS path,
            COALESCE(m.f_domain,'SHARED') AS domain,
            COALESCE(m.f_show_staff_only,1) AS show_staff_only,
            CAST(m.f_flag AS UNSIGNED) AS flag,
            m.f_order AS menuOrder
          FROM tbl_m_menu m
          LEFT JOIN tbl_m_menu_subgroup sg ON sg.f_subgroupID = m.f_subgroupID AND sg.f_status = 1";
  $conds = [];
  $params = [];

  if (!$all && $modulID) { $conds[] = "m.f_modulID = ?"; $params[] = $modulID; }
  if ($all && $modulID)  { $conds[] = "m.f_modulID = ?"; $params[] = $modulID; }
  if ($active !== null)  { $conds[] = "m.f_flag = ?";    $params[] = $active; }

  if ($conds) $sql .= " WHERE ".implode(' AND ', $conds);
  $sql .= " ORDER BY m.f_modulID ASC,
                   COALESCE(sg.f_order, 0) ASC,
                   COALESCE(m.f_order, 99999) ASC,
                   m.f_menuID ASC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $result = ['error'=>false, 'menus'=>$menus, 'count'=>count($menus)];
  
  // Store in cache
  GroupDataCache::set($cacheKey, $result);
  header('X-Cache: MISS');
  echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  error_log('[menu-list] ' . $e->getMessage());
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

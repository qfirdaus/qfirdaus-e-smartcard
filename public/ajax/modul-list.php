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

// init projek (jika perlu session/lang)
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';

// ⬅️ path betul ikut structure: /classes/Database.php
require_once __DIR__ . '/../classes/Database.php';

// Rate limiting: max 30 requests per 60 seconds (read operation)
if (!checkRateLimit('modul_list', 30, 60)) {
  http_response_code(429);
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
  exit;
}

// Check cache (30 min TTL)
$lang = $_SESSION['lang'] ?? 'ms';
$withCounts = isset($_GET['withCounts']) ? (int)$_GET['withCounts'] : 0;
$onlyActive = isset($_GET['active']) ? (int)$_GET['active'] : null;
$cacheKey = 'modul_list_' . $lang . '_' . ($withCounts ? 'wc' : '') . '_' . ($onlyActive ?? 'any');
$cached = GroupDataCache::get($cacheKey, 1800);
if ($cached !== null) {
  header('X-Cache: HIT');
  echo json_encode($cached, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE); exit;
  }

  // MySQL sahaja untuk jadual modul/menu
  $pdo = Database::pdoMysql();
  ensureAjaxGroupManagePermission($pdo);

  $withCounts = isset($_GET['withCounts']) ? (int)$_GET['withCounts'] : 0;
  $onlyActive = isset($_GET['active']) ? (int)$_GET['active'] : null; // optional

  if ($withCounts) {
    // Kira menu per modul (flag=1 kalau nak yang aktif sahaja)
    $sql = "SELECT
              m.f_modulID      AS id,
              COALESCE(NULLIF(m.f_modulName_ms,''), NULLIF(m.f_modulName_en,''), CONCAT('Modul ', m.f_modulID)) AS nama,
              COUNT(CASE WHEN ".($onlyActive===1?'me.f_flag = 1':'1=1')." THEN me.f_menuID END) AS menuCount
            FROM tbl_m_modul m
            LEFT JOIN tbl_m_menu me ON me.f_modulID = m.f_modulID".
            ($onlyActive===1 ? " AND me.f_flag = 1" : "") ."
            GROUP BY m.f_modulID, m.f_modulName_ms, m.f_modulName_en, m.f_order
            ORDER BY COALESCE(m.f_order, 99999), m.f_modulID ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $result = ['error'=>false, 'moduls'=>$rows, 'count'=>count($rows)];
    GroupDataCache::set($cacheKey, $result);
    header('X-Cache: MISS');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // senarai modul ringkas
  $sql = "SELECT
            f_modulID AS id,
            COALESCE(NULLIF(f_modulName_ms,''), NULLIF(f_modulName_en,''), CONCAT('Modul ', f_modulID)) AS nama
          FROM tbl_m_modul
          ORDER BY COALESCE(f_order, 99999), f_modulID ASC";
  $moduls = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  $result = ['error'=>false, 'moduls'=>$moduls, 'count'=>count($moduls)];
  GroupDataCache::set($cacheKey, $result);
  header('X-Cache: MISS');
  echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  error_log('[modul-list] ' . $e->getMessage());
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

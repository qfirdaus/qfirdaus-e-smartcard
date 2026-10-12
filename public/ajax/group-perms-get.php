<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/group-perms-get.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $db = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($db);

  $gid = isset($_GET['groupID']) ? (int)$_GET['groupID'] : 0;
  if ($gid <= 0) { http_response_code(400); echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_invalid_id')], JSON_UNESCAPED_UNICODE); exit; }
  
  // Rate limiting: max 30 requests per 60 seconds (read operation)
  if (!checkRateLimit('group_perms_get', 30, 60)) {
    http_response_code(429);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
  }
  
  // Check cache (30 min TTL)
  $cacheKey = 'group_perms_' . $gid . '_' . ($_SESSION['lang'] ?? 'ms');
  $cached = GroupDataCache::get($cacheKey, 1800);
  if ($cached !== null) {
    header('X-Cache: HIT');
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
  }

  $lang = $_SESSION['lang'] ?? 'ms';
    // Only support 'ms' and 'en' — zh/ta columns removed from schema
    if (!in_array(strtolower($lang), ['ms','en'], true)) { $lang = 'ms'; }

  $stmt = $db->prepare("SELECT f_groupID, f_groupKod, f_groupName, COALESCE(f_modulAccess,'') AS f_modulAccess, COALESCE(f_menuAccess,'') AS f_menuAccess FROM tbl_m_group WHERE f_groupID=:g LIMIT 1");
  $stmt->execute([':g'=>$gid]);
  $grp = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$grp){ echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_not_found')], JSON_UNESCAPED_UNICODE); exit; }

  $toInts = function(string $csv){ $out=[]; foreach (explode(',', $csv) as $v){ $v=trim($v); if ($v!=='' && ctype_digit($v)) $out[]=(int)$v; } return array_values(array_unique($out)); };
  $modulIDs = $toInts($grp['f_modulAccess']);
  $menuIDs  = $toInts($grp['f_menuAccess']);

    // Fetch modules (only ms + en supported)
    $mods = $db->query(
      "SELECT f_modulID AS id, COALESCE(NULLIF(f_modulName_{$lang}, ''), NULLIF(f_modulName_ms,''), NULLIF(f_modulName_en,''), CONCAT('Modul ', f_modulID)) AS nama FROM tbl_m_modul ORDER BY COALESCE(f_order, 99999), f_modulID ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $nameColMenu = "m.f_menuName_{$lang}";
  $subgroupNameCol = $lang === 'en' ? 'sg.f_subgroupName_en' : 'sg.f_subgroupName_ms';
    // Fetch menus — only ms + en columns exist
    $menus = $db->query(
      "SELECT m.f_menuID AS id,
              m.f_modulID AS modulID,
              COALESCE(m.f_subgroupID, 0) AS subgroupID,
              COALESCE(NULLIF(" . $subgroupNameCol . ",''), NULLIF(sg.f_subgroupName_ms,''), NULLIF(sg.f_subgroupName_en,''), '') AS subgroupName,
              COALESCE(NULLIF(" . $nameColMenu . ",''), NULLIF(m.f_menuName_ms,''), NULLIF(m.f_menuName_en,''), CONCAT('Menu ', m.f_menuID)) AS nama,
              COALESCE(m.f_path,'') AS path
       FROM tbl_m_menu m
       LEFT JOIN tbl_m_menu_subgroup sg ON sg.f_subgroupID = m.f_subgroupID AND sg.f_status = 1
       ORDER BY m.f_modulID, COALESCE(sg.f_order, 0), COALESCE(m.f_order,99999), m.f_menuID"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $byMod = [];
  foreach($menus as $m){
    $mid = (int)$m['modulID'];
    if (!isset($byMod[$mid])) $byMod[$mid]=[];
    $byMod[$mid][] = [
      'id'=>(int)$m['id'],
      'nama'=>$m['nama'],
      'path'=>($m['path']!==''?$m['path']:null),
      'subgroupID'=>(int)($m['subgroupID'] ?? 0),
      'subgroupName'=>(string)($m['subgroupName'] ?? ''),
    ];
  }

  $result = [
    'error'=>false,
    'group'=>['id'=>(int)$grp['f_groupID'],'kod'=>$grp['f_groupKod'],'nama'=>$grp['f_groupName']],
    'modulIDs'=>$modulIDs,
    'menuIDs'=>$menuIDs,
    'modules'=>$mods,
    'menusByModul'=>$byMod
  ];
  
  // Store in cache
  GroupDataCache::set($cacheKey, $result);
  header('X-Cache: MISS');
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch(Throwable $e){
  http_response_code(500);
  error_log('[group-perms-get] ' . $e->getMessage());
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

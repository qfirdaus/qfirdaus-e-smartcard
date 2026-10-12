<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ======================================
// ✅ AJAX: Group Access (modules + menus + flags)
// Pulangkan senarai modul (ikut f_modulAccess kumpulan) dan menu di bawahnya,
// termasuk status ON/OFF dari tbl_m_menu.f_flag. Jika f_menuAccess ditetapkan,
// hanya menu yang tersenarai akan dipulangkan.
// ======================================
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  $pdo  = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($pdo);

  $gid = isset($_GET['groupID']) ? (int)$_GET['groupID'] : 0;
  if ($gid <= 0) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => __('userGroup_group_invalid_id')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  // Rate limiting: max 30 requests per 60 seconds (read operation)
  if (!checkRateLimit('group_access', 30, 60)) {
    http_response_code(429);
    echo json_encode(['error' => true, 'message' => __('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  
  // Only support 'ms' and 'en' (zh/ta columns removed)
  $lang = $_SESSION['lang'] ?? 'ms';
  $lang = in_array($lang, ['ms','en'], true) ? $lang : 'ms';

  // 1) Dapatkan akses kumpulan (modul & menu)
  $stmt = $pdo->prepare("SELECT f_modulAccess, f_menuAccess FROM tbl_m_group WHERE f_groupID = :id LIMIT 1");
  $stmt->execute([':id' => $gid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    echo json_encode(['modules' => [], 'totals' => ['modulCt' => 0, 'menuCt' => 0]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $modCsv = trim((string)$row['f_modulAccess']);
  $menuCsv = trim((string)$row['f_menuAccess']);

  // CSV → array int
  $groupModuleIds = array_values(array_filter(array_map(function($v){
    $v = trim($v);
    return ctype_digit($v) ? (int)$v : null;
  }, explode(',', $modCsv)), fn($v) => $v !== null));

  $menuFilterIds = array_values(array_filter(array_map(function($v){
    $v = trim($v);
    return ctype_digit($v) ? (int)$v : null;
  }, explode(',', $menuCsv)), fn($v) => $v !== null));

  // 2) Bina COALESCE nama modul ikut kolum yang wujud
  $colsMod = $pdo->query("SHOW COLUMNS FROM tbl_m_modul")->fetchAll(PDO::FETCH_COLUMN, 0);
  $candsMod = array_filter([
    "f_modulName_{$lang}",
    "f_modulName_ms", "f_modulName_en",
  ], fn($c) => in_array($c, $colsMod, true));
  $nameExprMod = $candsMod ? ('COALESCE('.implode(',', $candsMod).', CONCAT("Modul ", f_modulID))')
                           : 'CONCAT("Modul ", f_modulID)';

  // 3) Ambil nama modul
  $modulesMap = [];
  $orderedModuleIds = [];

  $sqlMod = "SELECT f_modulID, {$nameExprMod} AS nama, f_modulName_ms, f_modulName_en, f_icon, COALESCE(f_order, f_modulID) AS f_order
             FROM tbl_m_modul
             ORDER BY COALESCE(f_order, f_modulID), f_modulID";
  $stmMod = $pdo->query($sqlMod);
  while ($r = $stmMod->fetch(PDO::FETCH_ASSOC)) {
    $mid = (int)$r['f_modulID'];
    $modulesMap[$mid] = [
      'id' => $mid,
      'nama' => (string)$r['nama'],
      'modulNameMs' => (string)($r['f_modulName_ms'] ?? ''),
      'modulNameEn' => (string)($r['f_modulName_en'] ?? ''),
      'icon' => (string)($r['f_icon'] ?? ''),
      'order' => (int)($r['f_order'] ?? $mid),
      'menus' => [],
    ];
    $orderedModuleIds[] = $mid;
  }

  foreach ($groupModuleIds as $mid) {
    if (!isset($modulesMap[$mid])) {
      $modulesMap[$mid] = [
        'id' => $mid,
        'nama' => 'Modul ' . $mid,
        'modulNameMs' => 'Modul ' . $mid,
        'modulNameEn' => '',
        'icon' => '',
        'order' => $mid,
        'menus' => [],
      ];
      $orderedModuleIds[] = $mid;
    }
  }

  // 4) Bina COALESCE nama menu ikut kolum yang wujud
  $colsMenu = $pdo->query("SHOW COLUMNS FROM tbl_m_menu")->fetchAll(PDO::FETCH_COLUMN, 0);
  $candsMenu = array_filter([
    "f_menuName_{$lang}",
    "f_menuName_ms", "f_menuName_en",
  ], fn($c) => in_array($c, $colsMenu, true));
  $nameExprMenu = $candsMenu ? ('COALESCE('.implode(',', $candsMenu).', CONCAT("Menu ", f_menuID))')
                             : 'CONCAT("Menu ", f_menuID)';
  $subgroupNameCol = $lang === 'en' ? 'sg.f_subgroupName_en' : 'sg.f_subgroupName_ms';

  // 5) Kira bilangan menu sebenar bagi setiap modul (global, tidak ikut filter kumpulan)
  $moduleMenuTotals = [];
  $allModuleIds = array_values(array_unique(array_map(static fn(array $module): int => (int)$module['id'], array_values($modulesMap))));
  if ($allModuleIds) {
    $placeCount = implode(',', array_fill(0, count($allModuleIds), '?'));
    $sqlCount = "
      SELECT f_modulID, COUNT(*) AS total_menu
      FROM tbl_m_menu
      WHERE f_modulID IN ($placeCount)
      GROUP BY f_modulID
    ";
    $stmCount = $pdo->prepare($sqlCount);
    $stmCount->execute($allModuleIds);
    while ($c = $stmCount->fetch(PDO::FETCH_ASSOC)) {
      $moduleMenuTotals[(int)$c['f_modulID']] = (int)($c['total_menu'] ?? 0);
    }
  }

  // 6) Tarik menu di bawah modul; jika ada f_menuAccess → tapis guna CSV
  $menuCt = 0;
  if ($allModuleIds) {
    $place = implode(',', array_fill(0, count($allModuleIds), '?'));

    if (!empty($menuFilterIds)) {
      // Guna FIND_IN_SET dengan CSV original supaya kekal pantas & ringkas
      $sqlMenu = "
        SELECT m.f_menuID, m.f_modulID, {$nameExprMenu} AS nama, m.f_path, m.f_flag, COALESCE(m.f_order, 99999) AS menuOrder,
               COALESCE(m.f_subgroupID, 0) AS subgroupID,
               COALESCE(NULLIF({$subgroupNameCol}, ''), NULLIF(sg.f_subgroupName_ms, ''), NULLIF(sg.f_subgroupName_en, ''), '') AS subgroupName,
               COALESCE(sg.f_icon, 'ri-folder-2-line') AS subgroupIcon,
               COALESCE(sg.f_order, 0) AS subgroupOrder,
               COALESCE(m.f_domain, 'SHARED') AS f_domain,
               COALESCE(m.f_show_staff_only, 1) AS f_show_staff_only
        FROM tbl_m_menu m
        LEFT JOIN tbl_m_menu_subgroup sg ON sg.f_subgroupID = m.f_subgroupID AND sg.f_modulID = m.f_modulID AND COALESCE(sg.f_status, 1) = 1
        WHERE m.f_modulID IN ($place)
          AND FIND_IN_SET(m.f_menuID, ?) > 0
        ORDER BY m.f_modulID, COALESCE(sg.f_order, 0), COALESCE(m.f_order, 99999), m.f_menuID
      ";
      $params = array_merge($allModuleIds, [implode(',', $menuFilterIds)]);
    } else {
      $sqlMenu = "
        SELECT m.f_menuID, m.f_modulID, {$nameExprMenu} AS nama, m.f_path, m.f_flag, COALESCE(m.f_order, 99999) AS menuOrder,
               COALESCE(m.f_subgroupID, 0) AS subgroupID,
               COALESCE(NULLIF({$subgroupNameCol}, ''), NULLIF(sg.f_subgroupName_ms, ''), NULLIF(sg.f_subgroupName_en, ''), '') AS subgroupName,
               COALESCE(sg.f_icon, 'ri-folder-2-line') AS subgroupIcon,
               COALESCE(sg.f_order, 0) AS subgroupOrder,
               COALESCE(m.f_domain, 'SHARED') AS f_domain,
               COALESCE(m.f_show_staff_only, 1) AS f_show_staff_only
        FROM tbl_m_menu m
        LEFT JOIN tbl_m_menu_subgroup sg ON sg.f_subgroupID = m.f_subgroupID AND sg.f_modulID = m.f_modulID AND COALESCE(sg.f_status, 1) = 1
        WHERE m.f_modulID IN ($place)
        ORDER BY m.f_modulID, COALESCE(sg.f_order, 0), COALESCE(m.f_order, 99999), m.f_menuID
      ";
      $params = $allModuleIds;
    }

    $stmMenu = $pdo->prepare($sqlMenu);
    $stmMenu->execute($params);

    while ($m = $stmMenu->fetch(PDO::FETCH_ASSOC)) {
      $mid = (int)$m['f_modulID'];
      if (!isset($modulesMap[$mid])) continue;
      $modulesMap[$mid]['menus'][] = [
        'id'   => (int)$m['f_menuID'],
        'nama' => (string)$m['nama'],
        'path' => $m['f_path'] !== null ? (string)$m['f_path'] : null,
        'menuOrder' => (int)($m['menuOrder'] ?? 99999),
        'subgroupID' => (int)($m['subgroupID'] ?? 0),
        'subgroupName' => (string)($m['subgroupName'] ?? ''),
        'subgroupIcon' => (string)($m['subgroupIcon'] ?? 'ri-folder-2-line'),
        'subgroupOrder' => (int)($m['subgroupOrder'] ?? 0),
        'domain' => (string)($m['f_domain'] ?? 'SHARED'),
        'showStaffOnly' => (int)($m['f_show_staff_only'] ?? 1),
        'flag' => (int)$m['f_flag'] === 1 ? 1 : 0,
      ];
      $menuCt++;
    }
  }

  // 7) Susun ikut f_order modul (fallback kepada CSV asal jika ada modul tiada rekod)
  $modules = array_map(function ($id) use ($modulesMap, $moduleMenuTotals) {
    $module = $modulesMap[$id];
    $module['menuTotal'] = (int)($moduleMenuTotals[$id] ?? 0);
    $module['canDelete'] = ($module['menuTotal'] === 0);
    return $module;
  }, $orderedModuleIds);

  $result = [
    'modules' => $modules,
    'totals'  => ['modulCt' => count($modules), 'menuCt' => $menuCt]
  ];
  
  header('X-Cache: BYPASS');
  echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('[group-access] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}

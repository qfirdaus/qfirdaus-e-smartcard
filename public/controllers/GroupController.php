<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

// ======================================
// 🎯 Controller untuk Kumpulan Pengguna
// ======================================

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Group.php';

class GroupController {
  public string $lang = 'ms';
  public array  $profile = [];
  public array  $senaraiGroup = [];

  /** @var PDO */
  protected PDO $pdo_mysql;

  /** @var Group */
  protected Group $groupModel;

  public function __construct() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $this->lang = $_SESSION['lang'] ?? 'ms';

    // ✅ MySQL explicit
    $this->pdo_mysql  = Database::getInstance('mysql')->getConnection();

    // ✅ Profil pengguna
    $userModel  = new User($this->pdo_mysql);
    $f_stafID   = $_SESSION['f_stafID'] ?? null;
    $this->profile = $f_stafID ? ($userModel->getProfile($f_stafID) ?? []) : [];

    // ✅ Apply theme (guard json)
    $settingJson  = $this->profile['f_themeSetting'] ?? '{}';
    $themeSetting = json_decode($settingJson, true);
    if (!is_array($themeSetting)) { $themeSetting = []; }

    $_SESSION['theme.menu']   = $themeSetting['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
    $_SESSION['theme.topbar'] = $themeSetting['topbarColor']  ?? ($_SESSION['theme.topbar'] ?? 'light');
    $_SESSION['theme.layout'] = $themeSetting['layoutMode']   ?? ($_SESSION['theme.layout'] ?? 'light');

    // ✅ Data kumpulan (MySQL)
    $this->groupModel    = new Group($this->pdo_mysql);
    $this->senaraiGroup  = $this->groupModel->getAll();
  }

  public function setLang(string $lang): void {
    $this->lang = $lang ?: 'ms';
    $_SESSION['lang'] = $this->lang;
  }

  /* ======================================================================
   *                         API untuk Modal Akses
   * ==================================================================== */

  /**
   * Pulangkan butiran akses untuk kumpulan dalam bentuk:
   * [
   *   'modules' => [
   *      ['id'=>1,'kod'=>'1','nama'=>'Modul HR','menus'=>[
   *         ['id'=>3,'kod'=>'3','nama'=>'Dashboard','path'=>'/hr/dashboard'], ...
   *      ]],
   *      ...
   *   ],
   *   'totals' => ['modulCt'=>X, 'menuCt'=>Y]
   * ]
   *
   * Logik:
   * - f_modulAccess = CSV ID modul (rujuk PK tbl_m_modul.f_modulID)
   * - f_menuAccess  (jika ada nilai) = CSV ID menu yang dibenarkan (rujuk PK tbl_m_menu.f_menuID)
   *   - Jika kosong, ambil SEMUA menu di bawah modul-modul yang dibenarkan.
   */
  public function getAccessDetail(int $groupID): array {
    $grp = $this->getGroupById($groupID);
    if (!$grp) {
      return ['modules'=>[], 'totals'=>['modulCt'=>0, 'menuCt'=>0]];
    }

    // === 1) Parse akses dari rekod group ===
    $modIds  = $this->csvToIntList((string)($grp['f_modulAccess'] ?? '')); // modul IDs
    $menuIds = $this->csvToIntList((string)($grp['f_menuAccess']  ?? '')); // menu  IDs (optional)

    if (!$modIds && !$menuIds) {
      return ['modules'=>[], 'totals'=>['modulCt'=>0, 'menuCt'=>0]];
    }

    // === 2) Ambil metadata modul ikut bahasa ===
    $modulesMeta = $this->fetchModulesByIds($modIds);  // map id => ['id','nama']

    // === 3) Ambil menu ===
    // Jika group specify menu-level access, ikut senarai tu;
    // jika tidak, ambil semua menu di bawah modul-modul yang dibenarkan.
    if ($menuIds) {
      $menus = $this->fetchMenusByIds($menuIds);       // list dengan f_modulID
      // pastikan modul untuk menu luar senarai modul juga appear
      $menuModIds = array_values(array_unique(array_map(fn($m)=> (int)$m['modul_id'], $menus)));
      $missing    = array_values(array_diff($menuModIds, array_keys($modulesMeta)));
      if ($missing) {
        $extraMods = $this->fetchModulesByIds($missing);
        $modulesMeta = $modulesMeta + $extraMods;
      }
    } else {
      $menus = $this->fetchMenusByModuleIds($modIds);  // semua menu bawah modul
    }

    // === 4) Grouping menu → modul ===
    $moduleMap = [];
    // inisialisasi modul ikut turutan CSV asal
    foreach ($modIds as $mid) {
      $meta = $modulesMeta[$mid] ?? ['id'=>$mid, 'nama'=> 'Modul #'.$mid];
      $moduleMap[$mid] = [
        'id'    => $meta['id'],
        'kod'   => (string)$meta['id'], // untuk serasi UI sedia ada yang guna "kod"
        'nama'  => $meta['nama'],
        'menus' => [],
      ];
    }

    foreach ($menus as $me) {
      $mid = (int)$me['modul_id'];
      if (!isset($moduleMap[$mid])) {
        // kalau menu refer modul yang tak ada dalam f_modulAccess → masukkan juga
        $mmeta = $modulesMeta[$mid] ?? ['id'=>$mid, 'nama'=> 'Modul #'.$mid];
        $moduleMap[$mid] = [
          'id'    => $mmeta['id'],
          'kod'   => (string)$mmeta['id'],
          'nama'  => $mmeta['nama'],
          'menus' => [],
        ];
      }
      $moduleMap[$mid]['menus'][] = [
        'id'   => (int)$me['id'],
        'kod'  => (string)$me['id'], // serasi UI
        'nama' => (string)$me['nama'],
        'path' => $me['path'],
      ];
    }

    // === 5) Susun output: modul ikut turutan f_modulAccess, kemudian modul tambahan (jika ada)
    $modulesOut = [];
    $added = [];
    foreach ($modIds as $mid) {
      if (isset($moduleMap[$mid])) {
        $modulesOut[] = $moduleMap[$mid];
        $added[$mid] = true;
      }
    }
    foreach ($moduleMap as $mid => $row) {
      if (!isset($added[$mid])) $modulesOut[] = $row;
    }

    // === 6) Kiraan total menu
    $menuCt = 0; foreach ($modulesOut as $m) { $menuCt += count($m['menus']); }
    $result = [
      'modules' => $modulesOut,
      'totals'  => ['modulCt' => count($modulesOut), 'menuCt' => $menuCt]
    ];

    // Optional audit: record VIEW of group access details
    try {
      if (function_exists('audit_event')) {
        // Ensure helper available
        require_once __DIR__ . '/../setting/helper/audit_helper.php';

        $nama = $this->profile['f_nama'] ?? null;
        $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
        $actorLabel = function_exists('audit_format_actor_label') ? audit_format_actor_label($nama, $nostaf) : $nama;

        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;

        audit_event([
          'event_type' => 'VIEW',
          'severity' => 'INFO',
          'outcome' => 'SUCCESS',
          'target_type' => 'group_access',
          'target_id' => (string)$groupID,
          'target_label' => $grp['f_groupName'] ?? ('Group '.$groupID),
          'message' => audit_format_message('Group access viewed', $actorLabel),
          'request_id' => $requestId,
          'session_id' => session_id(),
          'user_id' => $_SESSION['f_stafID'] ?? null,
          'actor_label' => $actorLabel,
          'meta' => [
            'group_id' => $groupID,
            'modulCt' => count($modulesOut),
            'menuCt' => $menuCt,
            'source_page' => strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/'
          ]
        ]);
      }
    } catch (Throwable $e) {
      error_log('[GroupController::getAccessDetail] Audit error: ' . $e->getMessage());
    }

    return $result;
  }

  /**
   * Dapatkan satu rekod kumpulan melalui ID.
   * Fallback: iterate senaraiGroup jika model tak sediakan getById().
   */
  public function getGroupById(int $groupID): ?array {
    if ($groupID <= 0) return null;

    if (method_exists($this->groupModel, 'getById')) {
      $row = $this->groupModel->getById($groupID);
      if (is_array($row) && $row) return $row;
    }
    foreach ($this->senaraiGroup as $g) {
      if ((int)($g['f_groupID'] ?? 0) === $groupID) return $g;
    }
    return null;
  }

  /* ======================================================================
   *                               Helpers
   * ==================================================================== */

  /** CSV → int[], kekalkan turutan & unique */
  private function csvToIntList(string $csv): array {
    if ($csv === '') return [];
    $parts = array_map('trim', explode(',', $csv));
    $out = [];
    foreach ($parts as $p) {
      if ($p === '' || !is_numeric($p)) continue;
      $v = (int)$p;
      if (!in_array($v, $out, true)) $out[] = $v;
    }
    return $out;
  }

  /** Tentukan kolum nama ikut bahasa untuk modul */
  private function modulNameColumn(): string {
    switch (strtolower($this->lang)) {
      case 'en': return 'f_modulName_en';
      default:   return 'f_modulName_ms';
    }
  }

  /** Tentukan kolum nama ikut bahasa untuk menu */
  private function menuNameColumn(): string {
    switch (strtolower($this->lang)) {
      case 'en': return 'f_menuName_en';
      default:   return 'f_menuName_ms';
    }
  }

  /** Ambil modul berdasarkan ID → map [id] => ['id','nama'] */
  private function fetchModulesByIds(array $ids): array {
    $ids = array_values(array_filter($ids, fn($v)=>is_int($v) && $v>0));
    if (!$ids) return [];
    $colNama = $this->modulNameColumn();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT f_modulID AS id, COALESCE(NULLIF($colNama,''), f_modulName_ms) AS nama
            FROM tbl_m_modul WHERE f_modulID IN ($ph) ORDER BY FIELD(f_modulID, $ph)";
    // NOTE: FIELD() untuk kekalkan turutan CSV (letak ids dua kali dalam param)
    $params = array_merge($ids, $ids);

    $st = $this->pdo_mysql->prepare($sql);
    $st->execute($params);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $id = (int)$r['id'];
      $out[$id] = ['id'=>$id, 'nama'=> (string)$r['nama']];
    }
    // Pastikan semua id ada (kalau miss, fallback nama "Modul #ID")
    foreach ($ids as $id) if (!isset($out[$id])) $out[$id] = ['id'=>$id,'nama'=>'Modul #'.$id];
    return $out;
  }

  /** Ambil menu mengikut ID menu */
  private function fetchMenusByIds(array $ids): array {
    $ids = array_values(array_filter($ids, fn($v)=>is_int($v) && $v>0));
    if (!$ids) return [];
    $colNama = $this->menuNameColumn();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT f_menuID AS id,
                   COALESCE(NULLIF($colNama,''), f_menuName_ms) AS nama,
                   f_path AS path,
                   f_modulID AS modul_id
            FROM tbl_m_menu
            WHERE f_menuID IN ($ph)
            ORDER BY FIELD(f_menuID, $ph)";
    $params = array_merge($ids, $ids);
    $st = $this->pdo_mysql->prepare($sql);
    $st->execute($params);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out[] = [
        'id'       => (int)$r['id'],
        'nama'     => (string)$r['nama'],
        'path'     => (string)($r['path'] ?? ''),
        'modul_id' => (int)($r['modul_id'] ?? 0),
      ];
    }
    return $out;
  }

  /** Ambil SEMUA menu di bawah senarai modul */
  private function fetchMenusByModuleIds(array $modIds): array {
    $modIds = array_values(array_filter($modIds, fn($v)=>is_int($v) && $v>0));
    if (!$modIds) return [];
    $colNama = $this->menuNameColumn();
    $ph = implode(',', array_fill(0, count($modIds), '?'));
    $sql = "SELECT f_menuID AS id,
                   COALESCE(NULLIF($colNama,''), f_menuName_ms) AS nama,
                   f_path AS path,
                   f_modulID AS modul_id
            FROM tbl_m_menu
            WHERE f_modulID IN ($ph)
            ORDER BY f_modulID ASC, f_order ASC, f_menuID ASC";
    $st = $this->pdo_mysql->prepare($sql);
    $st->execute($modIds);

    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $out[] = [
        'id'       => (int)$r['id'],
        'nama'     => (string)$r['nama'],
        'path'     => (string)($r['path'] ?? ''),
        'modul_id' => (int)($r['modul_id'] ?? 0),
      ];
    }
    return $out;
  }
}

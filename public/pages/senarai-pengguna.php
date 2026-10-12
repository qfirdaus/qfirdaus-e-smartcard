<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/senarai-pengguna.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../ajax/_helpers.php';

// Set flag untuk Select2 (untuk load CSS & JS)
$NEED_SELECT2 = true;

require_once __DIR__ . '/../controllers/UserListController.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/functions-db.php';

$controller   = new UserListController();
$studentModeEnabled = function_exists('is_student_mode_enabled') ? is_student_mode_enabled() : false;

$lang         = $controller->lang ?? 'ms';
$profile      = $controller->profile ?? [];
$senaraiUser  = $controller->senaraiUser ?? [];
$senaraiUserStaf = $senaraiUser;
$senaraiUserPelajar = [];
$senaraiUserUmum = [];

// User model untuk getAvatarUrl
$dbMySQL = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($dbMySQL);
$userModel = new User($dbMySQL);
$schemaColumnExists = static function (PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $cacheKey = strtolower($table . '.' . $column);
  if (array_key_exists($cacheKey, $cache)) {
    return $cache[$cacheKey];
  }

  try {
    $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
      return $cache[$cacheKey] = false;
    }

    $stmt = $pdo->prepare(
      'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = :database
         AND TABLE_NAME = :table
         AND COLUMN_NAME = :column'
    );
    $stmt->execute([
      ':database' => $databaseName,
      ':table' => $table,
      ':column' => $column,
    ]);
    return $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
  } catch (Throwable $e) {
    return $cache[$cacheKey] = false;
  }
};

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// helper escape
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// format staf id: XXXX-XX jika 6 digit
function format_stafid(?string $id): string {
  $id = trim((string)$id);
  $raw = str_replace('-', '', $id);
  if ($raw !== '' && ctype_digit($raw) && strlen($raw) === 6) {
    return substr($raw,0,4) . '-' . substr($raw,4,2);
  }
  return $id;
}

function is_staff_option_record(array $row): bool {
  $nopekerja = trim((string)($row['nopekerja'] ?? ''));
  $idpekerja = trim((string)($row['idpekerja'] ?? ''));
  $jawatan   = trim((string)($row['jawatan'] ?? ''));
  $jabatan   = trim((string)($row['jabatan'] ?? ''));

  if ($nopekerja === '') {
    return false;
  }

  return $idpekerja !== '' || $jawatan !== '' || $jabatan !== '';
}

function group_category_matches_scope(?string $categoryUser, string $scope = 'staff'): bool {
  $category = strtoupper(trim((string)$categoryUser));
  return match (strtolower(trim($scope))) {
    'staff', 'staf' => $category === 'STAF',
    'student', 'pelajar' => $category === 'PELAJAR',
    'public', 'umum' => $category === 'UMUM',
    default => false,
  };
}

function is_protected_staff_account_local(?string $stafID): bool {
  if (function_exists('isProtectedStaffAccount')) {
    return isProtectedStaffAccount($stafID);
  }
  $normalized = preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim((string)$stafID))) ?? '';
  if ($normalized === '') return false;
  $protected = defined('PRESTASI_PROTECTED_STAFF_IDS') && is_array(PRESTASI_PROTECTED_STAFF_IDS)
    ? PRESTASI_PROTECTED_STAFF_IDS
    : [];
  foreach ($protected as $candidate) {
    $candidateNormalized = preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim((string)$candidate))) ?? '';
    if ($candidateNormalized !== '' && $candidateNormalized === $normalized) {
      return true;
    }
  }
  return false;
}

function can_self_manage_protected_staff_account_local(?string $targetStafID, string $currentUserStafIDNormalized = ''): bool {
  if (!is_protected_staff_account_local($targetStafID)) {
    return false;
  }

  $targetNormalized = preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim((string)$targetStafID))) ?? '';
  return $targetNormalized !== '' && $currentUserStafIDNormalized !== '' && $targetNormalized === $currentUserStafIDNormalized;
}

/**
 * Cache helper untuk user list page (session-based cache dengan TTL)
 */
final class UserListCache {
    private static string $namespace = 'userlist_cache';
    
    public static function get(string $key, int $ttl): mixed {
        $now = time();
        $c = $_SESSION[self::$namespace][$key] ?? null;
        if (!$c) return null;
        if (($c['ts'] + $ttl) < $now) {
            unset($_SESSION[self::$namespace][$key]);
            return null;
        }
        return $c['val'];
    }
    
    public static function set(string $key, mixed $val): void {
        if (!isset($_SESSION[self::$namespace])) {
            $_SESSION[self::$namespace] = [];
        }
        $_SESSION[self::$namespace][$key] = ['ts' => time(), 'val' => $val];
    }
    
    public static function clear(?string $prefix = null): void {
        if (!isset($_SESSION[self::$namespace])) return;
        if ($prefix === null) {
            unset($_SESSION[self::$namespace]);
            return;
        }
        foreach (array_keys($_SESSION[self::$namespace]) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset($_SESSION[self::$namespace][$k]);
            }
        }
    }
}

// Get current user's group (used for tracking/logging only; no page-level permission checks)
$currentUserGroup = $profile['f_groupKod'] ?? '';
$currentUserId = (int)($profile['f_userID'] ?? ($_SESSION['f_userID'] ?? 0));
$currentUserStafID = format_stafid((string)($profile['f_stafID'] ?? ($_SESSION['f_stafID'] ?? '')));
$currentUserNoPekerja = (string)($profile['f_nopekerja'] ?? ($_SESSION['f_nopekerja'] ?? ''));
$currentUserStafIDNormalized = str_replace('-', '', $currentUserStafID);
$currentUserNoPekerjaNormalized = str_replace('-', '', $currentUserNoPekerja);
$roleAdminSaId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
$roleAdminHrId = defined('PRESTASI_ROLE_ID_ADM_HR') ? (int)PRESTASI_ROLE_ID_ADM_HR : 0;
$roleAdminKeId = defined('PRESTASI_ROLE_ID_ADM_KE') ? (int)PRESTASI_ROLE_ID_ADM_KE : 0;
$roleAdminSaKod = defined('PRESTASI_ROLE_KOD_ADM_SA') ? (string)PRESTASI_ROLE_KOD_ADM_SA : (defined('PRESTASI_ROLE_ADM_SA') ? (string)PRESTASI_ROLE_ADM_SA : 'ADM-SA');
$roleAdminHrKod = defined('PRESTASI_ROLE_ADM_HR') ? (string)PRESTASI_ROLE_ADM_HR : 'ADM-HR';
$roleAdminKeKod = defined('PRESTASI_ROLE_ADM_KE') ? (string)PRESTASI_ROLE_ADM_KE : 'ADM-KE';
$isSuperAdmin = function_exists('is_user_super_admin') ? is_user_super_admin($profile, $dbMySQL) : ($roleAdminSaId > 0 && (int)($profile['f_groupID'] ?? 0) === $roleAdminSaId);
$canAddUsers = function_exists('userListCanAddUsers') ? userListCanAddUsers($dbMySQL, $profile) : $isSuperAdmin;
$canEditUsers = function_exists('userListCanEditTargetUser') ? userListCanEditTargetUser($dbMySQL, [], $profile) : $isSuperAdmin;
$canDeleteUsers = function_exists('userListCanDeleteTargetUser') ? userListCanDeleteTargetUser($dbMySQL, [], $profile) : $isSuperAdmin;

// ======================= Load Group List (fresh DB for style consistency) =======================
$senaraiGroup = [];
$senaraiGroupStaf = [];
$senaraiGroupPelajar = [];
$senaraiGroupUmum = [];
$assignableGroup = [];
$assignableGroupStaf = [];
$assignableGroupPelajar = [];
$assignableGroupUmum = [];
try {
    $groupSelect = [
      'f_groupID',
      'f_groupKod',
      'f_groupName',
      'f_categoryUser',
      $schemaColumnExists($dbMySQL, 'tbl_m_group', 'f_badge_class') ? 'f_badge_class' : "'' AS f_badge_class",
      $schemaColumnExists($dbMySQL, 'tbl_m_group', 'f_row_class') ? 'f_row_class' : "'' AS f_row_class",
      $schemaColumnExists($dbMySQL, 'tbl_m_group', 'f_color') ? 'f_color' : "'' AS f_color",
    ];
    $groupSql = "SELECT " . implode(', ', $groupSelect) . " FROM tbl_m_group ORDER BY f_groupName ASC";
    $groupStmt = $dbMySQL->query($groupSql);
    $senaraiGroup = $groupStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $senaraiGroupStaf = array_values(array_filter(
      $senaraiGroup,
      static fn($row) => is_array($row) && group_category_matches_scope((string)($row['f_categoryUser'] ?? ''), 'staff')
    ));
    $senaraiGroupPelajar = array_values(array_filter(
      $senaraiGroup,
      static fn($row) => is_array($row) && group_category_matches_scope((string)($row['f_categoryUser'] ?? ''), 'student')
    ));
    $senaraiGroupUmum = array_values(array_filter(
      $senaraiGroup,
      static fn($row) => is_array($row) && group_category_matches_scope((string)($row['f_categoryUser'] ?? ''), 'public')
    ));
    $canAssignGroup = static function ($row) use ($dbMySQL, $profile): bool {
      return is_array($row) && (!function_exists('userListCanAssignGroup') || userListCanAssignGroup($dbMySQL, $row, $profile));
    };
    $assignableGroup = array_values(array_filter($senaraiGroup, $canAssignGroup));
    $assignableGroupStaf = array_values(array_filter($senaraiGroupStaf, $canAssignGroup));
    $assignableGroupPelajar = array_values(array_filter($senaraiGroupPelajar, $canAssignGroup));
    $assignableGroupUmum = array_values(array_filter($senaraiGroupUmum, $canAssignGroup));
} catch (Throwable $e) {
    error_log('[senarai-pengguna] Error loading groups: ' . $e->getMessage());
    $senaraiGroup = [];
    $senaraiGroupStaf = [];
    $senaraiGroupPelajar = [];
    $senaraiGroupUmum = [];
    $assignableGroup = [];
    $assignableGroupStaf = [];
    $assignableGroupPelajar = [];
    $assignableGroupUmum = [];
}

// ======================= Data-Driven UI Style Map (Group) =======================
$groupUiMaps = prestasi_group_ui_load_maps($dbMySQL, $senaraiGroup);
$groupUiById = $groupUiMaps['by_id'] ?? [];
$groupUiByCode = $groupUiMaps['by_code'] ?? [];
$showGroupUiDebug = $isSuperAdmin && (string)($_GET['debug_group_ui'] ?? '') === '1';

// Build dynamic row highlight CSS based on tbl_m_group.f_color (no manual CSS per role needed).
$groupDynamicCssRules = [];
foreach ($senaraiGroup as $gRow) {
  $gId = (int)($gRow['f_groupID'] ?? 0);
  $gKod = (string)($gRow['f_groupKod'] ?? '');
  $resolved = prestasi_group_ui_resolve($groupUiMaps, $gId, $gKod);
  $rowClass = trim((string)($resolved['rowClass'] ?? ''));
  $rowColor = trim((string)($resolved['rowColor'] ?? ''));
  if ($rowClass === '' || $rowColor === '') continue;
  $safeClass = preg_replace('/[^a-zA-Z0-9_-]+/', '', $rowClass) ?? '';
  if ($safeClass === '') continue;
  $safeColor = trim((string)$rowColor);
  if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $safeColor) && !preg_match('/^[a-zA-Z]+$/', $safeColor)) continue;
  $groupDynamicCssRules[$safeClass] = $safeColor;
}

try {
  $userSearch = $controller->q ?? '';
  $senaraiUserPelajar = load_users_by_category($dbMySQL, 'PELAJAR', $userSearch);
  $senaraiUserUmum = load_users_by_category($dbMySQL, 'UMUM', $userSearch);
} catch (Throwable $e) {
  error_log('[senarai-pengguna] Error loading scoped users: ' . $e->getMessage());
  $senaraiUserPelajar = [];
  $senaraiUserUmum = [];
}

function group_badge_inline_style(array $groupStyle): string {
  $rowColor = trim((string)($groupStyle['rowColor'] ?? ''));
  if ($rowColor === '') return '';
  if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $rowColor) && !preg_match('/^[a-zA-Z]+$/', $rowColor)) return '';
  $textColor = '#ffffff';
  if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $rowColor)) {
    $hex = ltrim($rowColor, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $textColor = $luminance >= 160 ? '#1e293b' : '#ffffff';
  }
  return 'background-color: ' . $rowColor . '; color: ' . $textColor . ';';
}

function load_users_by_category(PDO $pdo, string $category, string $q = ''): array {
  $userSchema = new User($pdo);
  $hasNickname = $userSchema->authTableHasColumn('f_nickname');
  $hasEmail = $userSchema->authTableHasColumn('f_email');
  $hasHandphone = $userSchema->authTableHasColumn('f_handphone');
  $hasNokp = $userSchema->authTableHasColumn('f_nokp');
  $hasAutoProvisioned = $userSchema->authTableHasColumn('f_isAutoProvisioned');
  $hasIdentitySource = $userSchema->authTableHasColumn('f_identitySource');

  $where = [
    "COALESCE(u.f_statusID,0) <> 9",
    "TRIM(COALESCE(u.f_categoryUser, '')) = :category",
  ];
  $params = [':category' => $category];

  if ($q !== '') {
    $where[] = "(u.f_nama LIKE :q OR u.f_loginID LIKE :q OR u.f_stafID LIKE :q OR u.f_nopekerja LIKE :q)";
    $params[':q'] = '%' . $q . '%';
  }

  $whereSql = 'WHERE ' . implode(' AND ', $where);
  $selectFields = [
    'u.f_userID',
    'u.f_loginID',
    'u.f_stafID',
    $hasNickname ? 'u.f_nickname' : "'' AS f_nickname",
    $hasEmail ? 'u.f_email' : "'' AS f_email",
    $hasHandphone ? 'u.f_handphone' : "'' AS f_handphone",
    $hasNokp ? 'u.f_nokp' : "'' AS f_nokp",
    'u.f_nopekerja',
    'u.f_nama',
    'u.f_categoryUser',
    'u.f_namajabatan',
    'u.f_jawatan',
    'u.f_status',
    'u.f_flag',
    $hasAutoProvisioned ? 'COALESCE(u.f_isAutoProvisioned, 0) AS f_isAutoProvisioned' : '0 AS f_isAutoProvisioned',
    $hasIdentitySource ? "TRIM(COALESCE(u.f_identitySource, '')) AS f_identitySource" : "'' AS f_identitySource",
    'u.f_groupID',
    'TRIM(u.f_groupKod) AS f_groupKod',
    "COALESCE(NULLIF(TRIM(g.f_groupName), ''), TRIM(u.f_groupKod)) AS f_groupName",
  ];
  $sql = "
    SELECT
      " . implode(",\n      ", $selectFields) . "
    FROM tbl_m_user u
    LEFT JOIN tbl_m_group g
      ON g.f_groupID = u.f_groupID
    $whereSql
    ORDER BY u.f_nama ASC
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  if (empty($rows)) {
    return [];
  }

  $userIds = [];
  $stafIds = [];
  foreach ($rows as $u) {
    $uid = (int)($u['f_userID'] ?? 0);
    if ($uid > 0) {
      $userIds[] = $uid;
      continue;
    }
    $sid = trim((string)($u['f_stafID'] ?? ''));
    if ($sid !== '') {
      $stafIds[] = $sid;
    }
  }
  $userIds = array_values(array_unique($userIds));
  $stafIds = array_values(array_unique($stafIds));
  if (empty($userIds) && empty($stafIds)) {
    return $rows;
  }

  $mapByUserId = [];
  if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sqlExtraByUser = "
      SELECT a.f_userID, g.f_groupName
      FROM tbl_ref_access a
      JOIN tbl_m_group g ON g.f_groupID = a.f_groupID
      JOIN tbl_m_user u ON u.f_userID = a.f_userID
      WHERE a.f_status = 1
        AND a.f_userID IN ($placeholders)
        AND a.f_groupID <> u.f_groupID
        AND TRIM(COALESCE(g.f_categoryUser, '')) = ?
      ORDER BY g.f_groupName ASC
    ";
    $stmtByUser = $pdo->prepare($sqlExtraByUser);
    $execParams = $userIds;
    $execParams[] = $category;
    $stmtByUser->execute($execParams);
    $extraRowsByUser = $stmtByUser->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($extraRowsByUser as $r) {
      $uid = (int)($r['f_userID'] ?? 0);
      $rname = (string)($r['f_groupName'] ?? '');
      if ($uid <= 0 || $rname === '') continue;
      $mapByUserId[$uid][] = $rname;
    }
  }

  $mapByStafId = [];
  if (!empty($stafIds)) {
    $placeholders = implode(',', array_fill(0, count($stafIds), '?'));
    $sqlExtra = "
      SELECT a.f_stafID, g.f_groupName
      FROM tbl_ref_access a
      JOIN tbl_m_group g ON g.f_groupID = a.f_groupID
      JOIN tbl_m_user u ON u.f_stafID = a.f_stafID
      WHERE a.f_status = 1
        AND a.f_userID IS NULL
        AND a.f_stafID IN ($placeholders)
        AND a.f_groupID <> u.f_groupID
        AND TRIM(COALESCE(g.f_categoryUser, '')) = ?
      ORDER BY g.f_groupName ASC
    ";
    $stmtX = $pdo->prepare($sqlExtra);
    $execParams = $stafIds;
    $execParams[] = $category;
    $stmtX->execute($execParams);
    $extraRows = $stmtX->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($extraRows as $r) {
      $sid = (string)($r['f_stafID'] ?? '');
      $rname = (string)($r['f_groupName'] ?? '');
      if ($sid === '' || $rname === '') continue;
      $mapByStafId[$sid][] = $rname;
    }
  }

  foreach ($rows as &$u) {
    $uid = (int)($u['f_userID'] ?? 0);
    $sid = trim((string)($u['f_stafID'] ?? ''));
    $extra = $uid > 0 ? ($mapByUserId[$uid] ?? []) : ($mapByStafId[$sid] ?? []);
    $u['extra_roles'] = $extra;
    $u['extra_roles_count'] = count($extra);
  }
  unset($u);

  return $rows;
}

function render_user_access_table(
  string $tableId,
  array $users,
  User $userModel,
  array $groupUiMaps,
  int $currentUserId,
  string $currentUserStafIDNormalized,
  string $currentUserNoPekerjaNormalized,
  bool $isSuperAdmin,
  PDO $policyPdo,
  array $policyProfile,
  string $scope = 'staff',
  ?string $nameColumnLabel = null,
  ?string $departmentColumnLabel = null,
  bool $showPositionColumn = true
): void {
  $scopeValue = strtolower(trim($scope)) ?: 'staff';
  $resolvedNameColumnLabel = $nameColumnLabel ?: (string)__('userList_col_name_staffid');
  $resolvedDepartmentColumnLabel = $departmentColumnLabel ?: (string)__('userList_col_department');
  ?>
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive dt-standard">
            <table class="table table-bordered align-middle user-access-table<?= $showPositionColumn ? '' : ' user-access-table--sixcol' ?>" id="<?= h($tableId) ?>" data-has-position="<?= $showPositionColumn ? '1' : '0' ?>">
              <thead>
                <tr>
                  <th class="col-bil"><?= __('userList_col_no') ?></th>
                  <th class="col-nama"><?= h($resolvedNameColumnLabel) ?></th>
                  <th class="col-jabatan"><?= h($resolvedDepartmentColumnLabel) ?></th>
                  <?php if ($showPositionColumn): ?>
                  <th class="col-jawatan"><?= __('userList_col_position') ?></th>
                  <?php endif; ?>
                  <th class="col-group"><?= __('userList_col_group') ?></th>
                  <th class="col-akses"><?= __('userList_col_access') ?></th>
                  <th class="col-actions"><?= __('userList_col_actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($users)): ?>
                  <?php foreach ($users as $rowIndex => $u): ?>
                    <?php
                      $userID  = (int)($u['f_userID'] ?? 0);
                      $nama    = (string)($u['f_nama'] ?? '');
                      $stafID  = format_stafid((string)($u['f_stafID'] ?? ''));
                      $loginID = trim((string)($u['f_loginID'] ?? ''));
                      $nickname = trim((string)($u['f_nickname'] ?? ''));
                      $email = trim((string)($u['f_email'] ?? ''));
                      $phone = trim((string)($u['f_handphone'] ?? ''));
                      $nokp = trim((string)($u['f_nokp'] ?? ''));
                      $jabatan = (string)($u['f_namajabatan'] ?? '');
                      $jawatan = (string)($u['f_jawatan'] ?? '');
                      $gId     = (int)($u['f_groupID'] ?? 0);
                      $gKod    = (string)($u['f_groupKod'] ?? '');
                      $gName   = (string)($u['f_groupName'] ?? $gKod);
                      $extraRoles = $u['extra_roles'] ?? [];
                      if (!is_array($extraRoles)) $extraRoles = [];
                      $extraCount = (int)($u['extra_roles_count'] ?? count($extraRoles));
                      $f_flag  = (int)($u['f_flag'] ?? 1);
                      $f_nopekerja = (string)($u['f_nopekerja'] ?? '');
                      $avatarUrl = $userModel->resolveAvatarUrl($u);
                      $isProtectedAccount = is_protected_staff_account_local($stafID);
                      $isAutoProvisioned = (int)($u['f_isAutoProvisioned'] ?? 0) === 1;
                      $identitySource = strtoupper(trim((string)($u['f_identitySource'] ?? '')));
                      $isSsoIdentity = $identitySource === 'SSO';
                      $showSsoIndicator = $isAutoProvisioned || $isSsoIdentity;
                      $ssoIndicatorTooltip = $isAutoProvisioned
                        ? sprintf((string)__('userList_auto_provisioned_tooltip'), $identitySource !== '' ? $identitySource : 'SSO')
                        : (string)__('userList_sso_identity_tooltip');
                      $isCurrentLoggedInUser =
                        ($currentUserId > 0 && $userID === $currentUserId) ||
                        ($currentUserStafIDNormalized !== '' && str_replace('-', '', $stafID) === $currentUserStafIDNormalized) ||
                        ($currentUserNoPekerjaNormalized !== '' && str_replace('-', '', $f_nopekerja) === $currentUserNoPekerjaNormalized);
                      $canManageProtectedSelf = can_self_manage_protected_staff_account_local($stafID, $currentUserStafIDNormalized);
                      $isTargetSuperAdmin = strtoupper(trim($gKod)) === 'ADM-SA';
                      $canEditThisUser = function_exists('userListCanEditTargetUser')
                        ? userListCanEditTargetUser($policyPdo, $u, $policyProfile)
                        : ($isSuperAdmin && (!$isProtectedAccount || $canManageProtectedSelf));
                      $canDeleteThisUser = function_exists('userListCanDeleteTargetUser')
                        ? (userListCanDeleteTargetUser($policyPdo, $u, $policyProfile) && !$isCurrentLoggedInUser && !$isProtectedAccount)
                        : ($isSuperAdmin && !$isCurrentLoggedInUser && !$isProtectedAccount);
                      $canViewAsUser = $isSuperAdmin && !$isCurrentLoggedInUser && !$isProtectedAccount && !$isTargetSuperAdmin && $f_flag === 1 && $loginID !== '';
                    ?>
                    <?php
                      $groupStyle = prestasi_group_ui_resolve($groupUiMaps, $gId, $gKod);
                      $rowClass = (string)($groupStyle['rowClass'] ?? '');
                      $badgeInlineStyle = group_badge_inline_style($groupStyle);
                    ?>
                    <tr data-user-id="<?= h((string)$userID) ?>" data-user-scope="<?= h($scopeValue) ?>" data-group-id="<?= h((string)$gId) ?>" data-group-kod="<?= h($gKod) ?>" data-row-class="<?= h($rowClass) ?>" data-flag="<?= h((string)$f_flag) ?>" data-extra-count="<?= h((string)$extraCount) ?>" data-extra-roles="<?= h(implode(', ', $extraRoles)) ?>" class="<?= h($rowClass) ?>">
                      <td class="col-bil"><?= (int)$rowIndex + 1 ?></td>
                      <?php $visibleIdentifier = $scopeValue === 'public' ? ($loginID !== '' ? $loginID : $stafID) : $stafID; ?>
                      <td class="col-nama">
                        <div class="user-name-shell">
                          <span class="truncate-1line cell-tooltip-text" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($nama . ' (' . $visibleIdentifier . ')') ?>"><?= h($nama) ?> (<?= h($visibleIdentifier) ?>)</span>
                          <?php if ($showSsoIndicator || $isProtectedAccount): ?>
                            <span class="user-name-indicators">
                              <?php if ($showSsoIndicator): ?>
                                <span class="auto-provisioned-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($ssoIndicatorTooltip) ?>"><i class="ri-user-add-line"></i></span>
                              <?php endif; ?>
                              <?php if ($isProtectedAccount): ?>
                                <span class="protected-account-badge" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h(__('userList_protected_tooltip')) ?>"><?= h(__('userList_protected_badge')) ?></span>
                              <?php endif; ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="col-jabatan"><span class="truncate-1line cell-tooltip-text" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($jabatan) ?>"><?= h($jabatan) ?></span></td>
                      <?php if ($showPositionColumn): ?>
                      <td class="col-jawatan"><span class="truncate-1line"><?= h($jawatan) ?></span></td>
                      <?php endif; ?>
                      <td class="col-group">
                        <span class="cell-inline">
                          <span class="group-chip cell-tooltip-text" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($gName) ?>"<?= $badgeInlineStyle !== '' ? ' style="' . h($badgeInlineStyle) . '"' : '' ?>><?= h($gName) ?></span>
                          <i class="ri-information-line text-muted extra-roles-info"
                             data-has-extra="<?= !empty($extraRoles) ? '1' : '0' ?>"
                             data-bs-toggle="tooltip"
                             data-bs-placement="top"
                             title="<?= h(!empty($extraRoles) ? implode(', ', $extraRoles) : __('userList_role_none')) ?>">
                          </i>
                        </span>
                      </td>
                      <td class="col-akses">
                        <?php if ($f_flag == 1): ?>
                          <span class="access-chip is-allowed cell-tooltip-text" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h((string)__('userList_access_granted')) ?>"><?= __('userList_access_granted') ?></span>
                        <?php else: ?>
                          <span class="access-chip is-blocked cell-tooltip-text" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h((string)__('userList_access_blocked')) ?>"><?= __('userList_access_blocked') ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="col-actions">
                        <?php if ($canEditThisUser || $canDeleteThisUser || $canViewAsUser): ?>
                          <?php if ($canViewAsUser): ?>
                            <button type="button"
                              class="btn btn-outline-warning btn-sm icon-btn btn-view-as-user"
                              title="<?= h(__('impersonation_view_as_action')) ?>"
                              data-loginid="<?= h($loginID) ?>"
                              data-nama="<?= h($nama) ?>"
                              data-displayid="<?= h($visibleIdentifier) ?>">
                              <i class="ri-eye-line"></i>
                            </button>
                          <?php endif; ?>
                          <?php if ($canEditThisUser): ?>
                          <button type="button"
                            class="btn btn-outline-primary btn-sm icon-btn btn-edit-group<?= $canViewAsUser ? ' ms-1' : '' ?>"
                            title="<?= h(__('userList_action_change_group')) ?>"
                            data-user-id="<?= h((string)$userID) ?>"
                            data-nama="<?= h($nama) ?>"
                            data-stafid="<?= h($stafID) ?>"
                            data-loginid="<?= h($loginID) ?>"
                            data-nickname="<?= h($nickname) ?>"
                            data-email="<?= h($email) ?>"
                            data-phone="<?= h($phone) ?>"
                            data-nokp="<?= h($nokp) ?>"
                            data-university="<?= h($jabatan) ?>"
                            data-displayid="<?= h($visibleIdentifier) ?>"
                            data-nopekerja="<?= h($f_nopekerja) ?>"
                            data-avatar-url="<?= h($avatarUrl) ?>"
                            data-jabatan="<?= h($jabatan) ?>"
                            data-group-id="<?= h((string)$gId) ?>"
                            data-group-kod="<?= h($gKod) ?>"
                            data-group-name="<?= h($gName) ?>"
                            data-scope="<?= h($scopeValue) ?>"
                            data-flag="<?= h((string)$f_flag) ?>">
                            <i class="ri-pencil-line"></i>
                          </button>
                          <?php endif; ?>
                          <?php if ($canDeleteThisUser): ?>
                            <button type="button"
                              class="btn btn-outline-danger btn-sm icon-btn btn-delete-user ms-1"
                              title="<?= h(__('userList_action_delete_user')) ?>"
                              data-user-id="<?= h((string)$userID) ?>"
                              data-nama="<?= h($nama) ?>"
                              data-stafid="<?= h($stafID) ?>"
                              data-displayid="<?= h($visibleIdentifier) ?>">
                              <i class="ri-delete-bin-line"></i>
                            </button>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
}

// ======================= Staf List: Lazy Load via AJAX (removed from page load) =======================
// Staf list akan di-load via AJAX endpoint (user-list-staf-options.php) dengan caching
// Ini mengurangkan initial page load time
// Namun, sediakan fallback dari session cache supaya dropdown ada data jika cache wujud
$senaraiStaf = [];
$existingStafIDs = [];
try {
  if (isset($_SESSION['userlist_cache']['staf_options_list']['val']) && is_array($_SESSION['userlist_cache']['staf_options_list']['val'])) {
    $senaraiStaf = array_values(array_filter(
      $_SESSION['userlist_cache']['staf_options_list']['val'],
      static fn($row) => is_array($row) && is_staff_option_record($row)
    ));
  }

  // Existing staf IDs digunakan untuk disable option jika user sudah wujud
  $dbMySQL = Database::getInstance('mysql')->getConnection();
  $existingStmt = $dbMySQL->query("SELECT DISTINCT f_stafID FROM tbl_m_user WHERE TRIM(COALESCE(f_categoryUser, '')) = 'STAF' AND f_stafID IS NOT NULL AND f_stafID <> ''");
  $existingRows = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
  $existingRaw = array_map('trim', array_filter($existingRows));
  $existingStafIDs = array_map(function($id){ return str_replace('-', '', $id); }, $existingRaw);
} catch (Throwable $e) {
  // Silent fallback - if DB/cache not available, JS will lazy-load via AJAX when modal opens
  $senaraiStaf = $senaraiStaf ?? [];
  $existingStafIDs = $existingStafIDs ?? [];
}

$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$PAGE_TITLE = (string)__('userList_page_heading_main');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  
  <!-- ✅ Select2 CSS (untuk dropdown) -->
  <link href="<?= base_url('assets/vendor/select2/css/select2.min.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <!-- ✅ Standard DataTables CSS (shared) -->
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= h($version) ?>"></script>
  
  <!-- ✅ Senarai APC Admin CSS (untuk table, dropdown, textbox styling) -->
  
  <style>
    /* Match kumpulan-pengguna table flow more closely */
    #userDT { width:100%; }
    #userDT th, #userDT td { vertical-align: middle; }
    /* Lebar kolum */
    #userDT th.col-bil,     #userDT td.col-bil     { width:5%;  text-align:center; }
    #userDT th.col-nama,    #userDT td.col-nama    { width:30%; text-align:left; }
    #userDT th.col-jabatan, #userDT td.col-jabatan { width:30%; text-align:left; }
    #userDT th.col-group,   #userDT td.col-group   { width:15%; text-align:left; }
    #userDT th.col-akses,   #userDT td.col-akses   { width:10%; text-align:left; }
    #userDT th.col-actions, #userDT td.col-actions { width:10%; text-align:center; }
    .truncate-1line { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .user-name-shell{
      display:flex;
      align-items:center;
      gap:.45rem;
      min-width:0;
    }
    .user-name-shell .truncate-1line{
      min-width:0;
      flex:1 1 auto;
    }
    .user-name-indicators{
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      flex:0 0 auto;
      margin-left:auto;
      padding-left:.35rem;
    }
    .protected-account-badge{
      display:inline-flex;
      align-items:center;
      flex:0 0 auto;
      padding:.18rem .5rem;
      border-radius:999px;
      border:1px solid rgba(217,119,6,.28);
      background:rgba(254,243,199,.95);
      color:#92400e;
      font-size:.68rem;
      font-weight:700;
      letter-spacing:.03em;
      white-space:nowrap;
    }
    .auto-provisioned-icon{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      flex:0 0 auto;
      width:1.35rem;
      height:1.35rem;
      border-radius:999px;
      border:1px solid rgba(14,165,233,.24);
      background:rgba(224,242,254,.98);
      color:#075985;
      font-size:.8rem;
      line-height:1;
      cursor:help;
    }
    .cell-tooltip-text{
      display:block;
      width:100%;
      max-width:100%;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      cursor:default;
    }
    .tooltip.userlist-cell-tooltip .tooltip-inner{
      max-width: 420px;
      text-align: left;
      white-space: normal;
      line-height: 1.25;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      word-break: break-word;
      padding: .5rem .7rem;
    }
    .icon-btn { line-height:1; }
    /* Bottom bar: info kiri, pagination kanan */
    .dt-bottom-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:nowrap; gap:.5rem; }
    .dt-bottom-row .dataTables_info { 
      margin:.25rem 0; 
      white-space: nowrap; /* ✅ Pastikan "Papar X rekod" dalam satu baris */
      line-height: 1.5;
    }
    .dt-bottom-row .dataTables_paginate { margin-left:auto; }
    /* ✅ DataTables length selector - pastikan dalam satu baris */
    .dataTables_length,
    #userDT_wrapper .dataTables_length {
      white-space: nowrap !important;
      line-height: 1.4;
      display: inline-block;
    }
    .dataTables_length label,
    #userDT_wrapper .dataTables_length label {
      white-space: nowrap !important;
      display: inline-flex !important;
      align-items: center;
      gap: 0.4rem;
      margin-bottom: 0;
      flex-wrap: nowrap !important;
      font-size: 0.875rem !important;
    }
    .dataTables_length select,
    #userDT_wrapper .dataTables_length select {
      display: inline-block !important;
      margin: 0 0.4rem !important;
      flex-shrink: 0 !important;
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      min-width: 70px !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    }
    .dataTables_length select:hover,
    #userDT_wrapper .dataTables_length select:hover {
      border-color: #ced4da !important;
    }
    .dataTables_length select:focus,
    #userDT_wrapper .dataTables_length select:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
      outline: none !important;
    }
    /* ✅ Pastikan text "Papar" dan "rekod" tidak wrap */
    .dataTables_length label > * {
      white-space: nowrap !important;
      display: inline !important;
    }
    /* ✅ Pastikan dt-top-left container tidak wrap */
    .dt-top-left {
        white-space: nowrap !important;
        flex-wrap: nowrap !important;
        padding-left: .55rem;
        position: relative !important;
        top: 7px !important;
      }
    .dt-top-left .dataTables_length {
      white-space: nowrap !important;
    }
    .dt-bottom-row .dataTables_info,
      #userDT_wrapper .dataTables_info {
        padding-left: .55rem;
        text-align: left !important;
        justify-content: flex-start !important;
      }
    /* ✅ Professional SweetAlert styling untuk sync success */
    .swal2-popup-custom {
      border-radius: 8px !important;
      box-shadow: 0 18px 44px rgba(15, 23, 42, 0.18) !important;
      padding: 1.1rem 1.1rem 1rem !important;
    }
    .swal2-title-custom {
      font-size: 1.18rem !important;
      font-weight: 700 !important;
      color: #1e293b !important;
      margin-bottom: .65rem !important;
    }
    .swal2-confirm-custom {
      padding: 0.65rem 1.6rem !important;
      font-size: 0.94rem !important;
      font-weight: 600 !important;
      border-radius: 8px !important;
      box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3) !important;
      transition: all 0.2s ease !important;
    }
    .swal2-confirm-custom:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 6px 16px rgba(25, 135, 84, 0.4) !important;
    }
    .sync-swal-wrap{
      text-align:left;
    }
    .sync-swal-banner{
      display:flex;
      align-items:flex-start;
      gap:.8rem;
      padding:.9rem 1rem;
      border:1px solid rgba(22,163,74,.14);
      border-radius:8px;
      background: linear-gradient(135deg, rgba(240,253,244,.96), rgba(248,250,252,.95));
      margin-bottom: .95rem;
    }
    .sync-swal-banner-icon{
      width:2.25rem;
      height:2.25rem;
      border-radius:999px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background: rgba(34,197,94,.14);
      color:#15803d;
      font-size:1.05rem;
      flex:0 0 auto;
    }
    .sync-swal-banner-title{
      font-size:.9rem;
      font-weight:700;
      color:#0f172a;
      margin-bottom:.18rem;
    }
    .sync-swal-banner-text{
      font-size:.84rem;
      line-height:1.45;
      color:#475569;
    }
    .sync-swal-card{
      border:1px solid rgba(148,163,184,.16);
      border-radius:8px;
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.95));
      padding:.95rem;
    }
    .sync-swal-card-title{
      display:flex;
      align-items:center;
      gap:.45rem;
      font-size:.78rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.06em;
      color:#64748b;
      margin-bottom:.75rem;
    }
    .sync-swal-stats{
      display:grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap:.7rem;
    }
    .sync-swal-stat{
      border:1px solid rgba(226,232,240,.95);
      border-radius:8px;
      background:#fff;
      padding:.7rem .78rem;
    }
    .sync-swal-stat-label{
      font-size:.72rem;
      text-transform:uppercase;
      letter-spacing:.06em;
      font-weight:700;
      color:#64748b;
      margin-bottom:.22rem;
    }
    .sync-swal-stat-value{
      font-size:1.02rem;
      font-weight:800;
      line-height:1.1;
    }
    .sync-swal-stat-value.is-success{ color:#15803d; }
    .sync-swal-stat-value.is-warning{ color:#b45309; }
    .sync-swal-stat-value.is-danger{ color:#b91c1c; }
    .sync-swal-stat-value.is-primary{ color:#1d4ed8; }
    /* ✅ Button styling untuk selari dengan dropdown list (Select2) */
    .dt-top-right button,
    #userDT_wrapper .dt-top-right button {
      height: 36px !important;
      min-height: 36px !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
      margin-left: 0 !important;
      margin-right: 0 !important;
      white-space: nowrap !important;
    }
    .dt-top-right button:hover,
    #userDT_wrapper .dt-top-right button:hover {
      border-color: #ced4da !important;
    }
    .dt-top-right button:focus,
    #userDT_wrapper .dt-top-right button:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    #userDT_wrapper #dtGroupFilter {
      min-width: 240px !important;
    }
    #userDT_wrapper .dt-top-right,
      #userDT_wrapper .dt-top-left {
        align-items: flex-start !important;
      }
    #userDT_wrapper .dt-top-left{
        padding-left: 1.4rem !important;
      }
    #userDT_wrapper .dt-top-left .dataTables_length,
      #userDT_wrapper .dt-top-left .dataTables_length label{
        margin-left: 0 !important;
      }
    #userDT_wrapper .dt-top-right > * {
      position: relative !important;
      top: 0 !important;
    }
    #userDT_wrapper .dt-bottom-row{
      display:flex !important;
      align-items:center !important;
      justify-content:space-between !important;
      flex-wrap:nowrap !important;
      width:100%;
      gap:.5rem !important;
      margin-top:0 !important;
      padding-top:.15rem !important;
    }
    #userDT_wrapper .dt-bottom-row > .dt-info-left{
      flex:0 1 auto !important;
      min-width:0 !important;
      overflow:hidden !important;
      display:flex !important;
      justify-content:flex-start !important;
      align-items:center !important;
      margin-right:auto !important;
    }
    #userDT_wrapper .dt-bottom-row > .dt-paging-right{
      flex:0 0 auto !important;
      margin-left:auto !important;
      display:flex !important;
      justify-content:flex-end !important;
      align-items:center !important;
      position:relative !important;
      top:-7px !important;
    }
    #userDT_wrapper .dataTables_paginate{
      margin-top:0 !important;
      white-space:nowrap !important;
      display:flex !important;
      align-items:center !important;
      justify-content:flex-end !important;
    }
    /* ✅ Remove gap antara button - gunakan gap pada container */
    .dt-top-right {
      gap: 0.5rem !important; /* Consistent gap */
    }
    .dt-top-right button + button {
      margin-left: 0 !important; /* Remove default margin */
    }
      /* Modal - Professional Styling */
      #userGroupModal,
      #roleExtraModal,
      #addUserModal {
        z-index: 11020 !important;
      }
      .modal,
      .modal-dialog,
      .modal-content,
      .modal-content::before,
      .modal-content::after {
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
      }
      .modal-dialog {
        border: 0 !important;
        background: transparent !important;
      }
      #userGroupModal .modal-dialog,
      #roleExtraModal .modal-dialog,
      #addUserModal .modal-dialog {
        position: relative;
        z-index: 1;
      }
      .modal.fade {
        transition: none !important;
      }
      .modal.fade .modal-dialog {
        transition: none !important;
        transform: none !important;
      }
      .modal.show .modal-dialog {
        transform: none !important;
      }
      .modal-content {
        border: none;
        border-radius: 8px;
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
        overflow: hidden;
      }
      .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0 0 8px 8px;
      }
      .modal-footer .btn {
        border-radius: 8px;
      }
      #userGroupModal .modal-dialog { 
        max-width: 720px;
      }
    #userGroupModal .modal-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-bottom: none;
      padding: 1rem 1.35rem;
    }
    #userGroupModal .modal-header .modal-title {
      color: white;
      font-weight: 600;
      font-size: 1.15rem;
      letter-spacing: 0.3px;
    }
    #userGroupModal .modal-header .btn-close {
      filter: invert(1);
      opacity: 0.9;
    }
    #userGroupModal .modal-header .btn-close:hover {
      opacity: 1;
    }
    #userGroupModal .modal-body {
      padding: 1rem 1.35rem;
    }
      #userGroupModal .modal-footer {
        padding: 0.95rem 1.35rem;
        background-color: #f8f9fa;
      }
      #userGroupModal .modal-content {
        border: none;
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
      }
    #userGroupModal .user-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #667eea;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
      margin: 0;
      display: block;
    }
    #userGroupModal .user-info-row {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
    }
    #userGroupModal .avatar-container {
      text-align: left;
      margin-bottom: 0;
      flex: 0 0 auto;
    }
    #userGroupModal .info-card {
      flex: 1 1 auto;
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
      border: 2px solid #e9ecef;
      border-radius: 8px;
      padding: 0.9rem;
      margin-bottom: 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    #userGroupModal .info-item {
      display: flex;
      align-items: flex-start;
      margin-bottom: 0.75rem;
      padding: 0.625rem;
      background-color: rgba(255,255,255,0.7);
      border-radius: 8px;
      transition: all 0.2s ease;
    }
    #userGroupModal .info-item:hover {
      background-color: rgba(255,255,255,0.9);
      transform: translateX(2px);
    }
    #userGroupModal .info-item:last-child {
      margin-bottom: 0;
    }
    #userGroupModal .info-icon {
      color: #667eea;
      font-size: 1.35rem;
      margin-right: 0.875rem;
      margin-top: 0.125rem;
      flex-shrink: 0;
    }
    #userGroupModal .info-content {
      flex: 1;
    }
    #userGroupModal .info-label {
      font-size: 0.75rem;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    #userGroupModal .info-value {
      font-size: 0.95rem;
      color: #212529;
      font-weight: 600;
      line-height: 1.4;
    }
    #userGroupModal .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 0.75rem;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
    }
    #userGroupModal .form-label i {
      margin-right: 0.5rem;
      font-size: 1.1rem;
    }
    #userGroupModal .form-select {
      min-height: 50px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      padding: 0.875rem 1rem;
      font-size: 1rem;
      transition: all 0.2s ease;
    }
    /* Compact select to match +Peranan button height */
    #userGroupModal .form-select.compact-select {
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.375rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      box-sizing: border-box !important;
    }
    #userGroupModal .btn.compact-btn {
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.375rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      display: inline-flex !important;
      align-items: center !important;
      border-width: 2px !important;
      box-sizing: border-box !important;
      vertical-align: middle !important;
    }
    #userGroupModal #ug_groupKod,
    #addUserModal #au_groupKod {
      width: 100% !important;
      max-width: 100% !important;
      display: block !important;
    }
    #userGroupModal .form-select:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
      #userGroupModal .modal-footer .btn {
        padding: 0.5rem 1.15rem;
        font-weight: 600;
      }
    /* Extra Role Modal (match Tambah Menu style) */
    #roleExtraModal .modal-dialog { max-width: 640px; }
    #roleExtraModal .modal-header {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: #fff;
    }
    #roleExtraModal .modal-body {
      padding: 1rem 1.35rem;
    }
    #roleExtraModal .modal-footer {
      padding: 0.95rem 1.35rem;
      background-color: #f8f9fa;
    }
    #roleExtraModal .modal-footer .btn {
      padding: 0.5rem 1.15rem;
      font-weight: 600;
    }
    #roleExtraModal .modal-footer .btn-primary {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      box-shadow: 0 4px 12px rgba(245, 87, 108, 0.3);
      border: none;
    }
    #roleExtraModal .role-list {
      display: grid;
      gap: 0.75rem;
    }
    #roleExtraModal .role-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      background: #fff;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    #roleExtraModal .role-item:hover {
      border-color: #f093fb;
      box-shadow: 0 4px 10px rgba(245, 87, 108, 0.12);
    }
    #roleExtraModal .role-item input[type="checkbox"] { transform: scale(1.1); }
    #roleExtraModal .role-label { font-weight: 600; color: #212529; }
    #userGroupModal #ug_error {
      margin-top: 1rem;
      border-radius: 8px;
      border-left: 4px solid #dc3545;
    }
    /* Section divider untuk layout yang lebih professional */
    #userGroupModal .form-section {
      margin-bottom: 1.25rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e9ecef;
    }
    #userGroupModal .form-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    #userGroupModal .form-section-title {
      font-size: 0.85rem;
      font-weight: 700;
      color: #495057;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 1.25rem;
      padding-bottom: 0.625rem;
      border-bottom: 3px solid #667eea;
      display: flex;
      align-items: center;
    }
    #userGroupModal .form-section-title i {
      margin-right: 0.5rem;
      font-size: 1rem;
      color: #667eea;
    }
    #userGroupModal #ug_publicSection .form-label {
      margin-bottom: 0.45rem;
      font-size: 0.875rem;
    }
    #userGroupModal #ug_publicSection .ug-public-grid {
      display: grid;
      gap: 1rem;
    }
    #userGroupModal #ug_publicSection .ug-public-card {
      border: 1px solid #e7ebf3;
      background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
      border-radius: 10px;
      padding: 0.95rem 1rem 1rem;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    #userGroupModal #ug_publicSection .ug-public-card-title {
      display: flex;
      align-items: center;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #475569;
      margin-bottom: 0.85rem;
      padding-bottom: 0.55rem;
      border-bottom: 1px solid #e8edf5;
    }
    #userGroupModal #ug_publicSection .ug-public-card-title i {
      color: #667eea;
    }
    #userGroupModal #ug_publicSection .ug-public-hint {
      display: flex;
      align-items: flex-start;
      gap: 0.35rem;
      font-size: 0.8rem;
      line-height: 1.45;
      color: #64748b;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.6rem 0.75rem;
      margin-bottom: 0.85rem;
    }
    #userGroupModal #ug_publicSection .form-label i {
      margin-right: 0.4rem;
      font-size: 1rem;
    }
    #userGroupModal #ug_publicSection .form-control {
      min-height: 38px;
      height: 38px;
      padding: 0.375rem 0.75rem;
      font-size: 0.875rem;
      border-radius: 6px;
    }
    #userGroupModal #ug_resetPassword,
    #userGroupModal #ug_resetPasswordConfirm,
    #userGroupModal #ug_publicPassword,
    #userGroupModal #ug_publicPasswordConfirm {
      min-height: 38px !important;
      height: 38px !important;
      padding: 0.375rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.5 !important;
      border-radius: 6px !important;
      border-width: 1px !important;
    }
    /* Validation blink effect */
    #userGroupModal .field-invalid {
      animation: fieldBlinkEdit 0.5s ease-in-out 3;
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    @keyframes fieldBlinkEdit {
      0%, 100% { 
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
      }
      50% { 
        border-color: #ff6b7a !important;
        box-shadow: 0 0 0 0.3rem rgba(220, 53, 69, 0.4) !important;
      }
    }
    /* Form field spacing */
    #userGroupModal .mb-3 {
      margin-bottom: 1.25rem !important;
    }
    #userGroupModal .mb-0 {
      margin-bottom: 0 !important;
    }
    
    /* Table shell aligned with kumpulan-pengguna.php */
    #userDT {
      border-radius: 8px;
      overflow: hidden;
      box-shadow: none;
      border: 1px solid rgba(148, 163, 184, 0.14);
      background: rgba(255, 255, 255, 0.96);
    }
    #userDT thead {
      background: transparent;
      color: inherit;
    }
    #userDT thead th {
      font-weight: 700;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      padding: 0.9rem 0.85rem;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, 0.16);
      color: #334155;
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.95) 100%);
    }
    #userDT tbody td {
      vertical-align: middle;
    }
    #userDT tbody tr {
      transition: background-color 0.18s ease, box-shadow 0.18s ease;
    }
    #userDT tbody tr:hover {
      background: rgba(241, 245, 249, 0.88) !important;
      transform: none;
      box-shadow: inset 0 0 0 999px rgba(241, 245, 249, 0.3);
    }
    #userDT tbody td {
      padding: 0.9rem 0.85rem;
      border-color: rgba(226, 232, 240, 0.9);
      vertical-align: middle;
    }
    /* Dark theme support */
    html[data-bs-theme="dark"] #userDT thead {
      background: transparent;
    }
    html[data-bs-theme="dark"] #userDT thead th {
      background: linear-gradient(180deg, rgba(30, 41, 59, 0.96) 0%, rgba(15, 23, 42, 0.94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148, 163, 184, 0.18);
    }
    html[data-bs-theme="dark"] #userDT tbody tr:hover {
      background: rgba(30, 41, 59, 0.76) !important;
      box-shadow: inset 0 0 0 999px rgba(30, 41, 59, 0.18);
    }
    /* Remove stripline effect untuk semua rows */
    #userDT tbody tr,
    #userDT tbody tr:nth-of-type(odd),
    #userDT tbody tr:nth-of-type(even) {
      background-color: transparent !important;
    }
    
    /* Keep group-specific row classes neutral so table rows match kumpulan-pengguna */
    #userDT tbody tr.row-group-adm-sa,
    #userDT tbody tr.row-group-adm-hr,
    <?php $groupCssKeys = array_keys($groupDynamicCssRules); foreach ($groupCssKeys as $index => $cssClass): ?>#userDT tbody tr.<?= h($cssClass) ?><?= $index < count($groupCssKeys) - 1 ? ',' : '' ?>
    <?php endforeach; ?>{
      background-color: transparent !important;
    }
    #userDT tbody tr.row-group-adm-sa td,
    #userDT tbody tr.row-group-adm-hr td,
    <?php foreach ($groupCssKeys as $index => $cssClass): ?>#userDT tbody tr.<?= h($cssClass) ?> td<?= $index < count($groupCssKeys) - 1 ? ',' : '' ?>
    <?php endforeach; ?>{
      background-color: transparent !important;
      background-image: none !important;
    }
    
    /* Highlight effect untuk row yang baru dikemas kini */
    #userDT tbody tr.row-updated-highlight {
      border-left: 4px solid #28a745 !important;
      box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.30) !important;
      animation: highlightPulse 1.2s ease-in-out infinite;
    }
    #userDT tbody tr.row-updated-highlight:hover {
      box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.40) !important;
    }
    @keyframes highlightPulse {
      0%, 100% { box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.28); }
      50% { box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.48); }
    }

    /* Carian di kanan, kemas dan sebaris */
    #userDT_wrapper .row.mb-2 { align-items: center; }
    #userDT_wrapper .dataTables_filter { text-align: right; }
    #userDT_wrapper .dataTables_filter label { 
      margin: 0 !important;
      font-size: 0.875rem !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 0.5rem !important;
    }
    #userDT_wrapper .dataTables_filter input {
      display: inline-block !important;
      width: 160px !important;
      max-width: 100% !important;
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    }
    #userDT_wrapper .dataTables_filter input:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
      outline: none !important;
    }
    
    /* ✅ Dropdown Filter Kumpulan - Compact size (sama dengan input carian dan button) */
    #dtGroupFilter,
    .dt-group-filter {
      width: 210px !important;
      min-width: 210px !important;
      max-width: 210px !important;
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
      white-space: nowrap !important;
      display: inline-block !important;
    }
    #dtGroupFilter:hover,
    .dt-group-filter:hover {
      border-color: #ced4da !important;
    }
    #dtGroupFilter:focus,
    .dt-group-filter:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
      outline: none !important;
    }

    /* Utility: larger select for group filter / modal */
    .big-select {
      font-size: 18px !important;
      padding: 0.6rem 1rem !important;
      min-width: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
      line-height: 1.4 !important;
      display: block !important;
      box-sizing: border-box !important;
    }
    
    /* Modal Tambah Pengguna - Professional Styling */
      #addUserModal .modal-dialog {
        max-width: 780px;
      }
      #addUserModal .modal-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border-bottom: none;
        padding: 1rem 1.35rem;
      }
      #addUserModal .modal-header .modal-title {
        color: white;
        font-weight: 600;
        font-size: 1.05rem;
        letter-spacing: 0.2px;
      }
      #addUserModal .modal-header .btn-close {
        filter: invert(1);
        opacity: 0.9;
      }
    #addUserModal .modal-header .btn-close:hover {
      opacity: 1;
    }
    #addUserModal .modal-body {
        padding: 1rem 1.35rem;
      }
    #addUserModal .au-modal-tabs {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid #e9ecef;
    }
    #addUserModal .au-modal-tabs .nav-link {
      border: 1px solid #dbe4f0;
      background: #f8fafc;
      color: #475569;
      border-radius: 8px;
      padding: 0.55rem 0.95rem;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.15s ease-in-out;
    }
    #addUserModal .au-modal-tabs .nav-link:hover {
      border-color: #cbd5e1;
      background: #f1f5f9;
      color: #334155;
    }
    #addUserModal .au-modal-tabs .nav-link.active {
      color: #fff;
      border-color: #28a745;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      box-shadow: 0 8px 20px rgba(32, 201, 151, 0.18);
    }
    #addUserModal .au-tab-pane {
      padding-top: 0.15rem;
    }
      #addUserModal .modal-footer {
        padding: 0.95rem 1.35rem;
        background-color: #f8f9fa;
      }
      #addUserModal .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.55rem;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
      }
      #addUserModal .form-label i {
        margin-right: 0.4rem;
        font-size: 1rem;
      }
      #addUserModal .select2-container--default .select2-selection--single {
        height: 38px !important;
        min-height: 38px !important;
        padding: 0.35rem 0.75rem !important;
        font-size: 0.875rem !important;
      }
      #addUserModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 0.1rem !important;
        padding-right: 24px !important;
        font-size: 0.875rem !important;
        line-height: 1.5 !important;
      }
      #addUserModal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 6px !important;
        width: 24px !important;
      }
      /* ✅ Select2 styling sama seperti senarai-apc-admin.php (untuk semua dropdowns) */
      .select2-container--default {
        font-size: 1rem !important;
        width: 100% !important;
      }
    .select2-container--default .select2-selection--single {
      height: 50px !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      padding: 0.875rem 1rem !important;
      font-size: 1rem !important;
      display: flex;
      align-items: center;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .select2-container--default .select2-selection--single:hover {
      border-color: #ced4da !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--single {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 1.5;
      padding-left: 0.5rem !important;
      padding-right: 30px !important;
      font-size: 1rem !important;
      color: #212529;
      font-weight: 600;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 48px !important;
      right: 10px !important;
      width: 30px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow b {
      border-color: #6c757d transparent transparent transparent;
      border-width: 6px 5px 0 5px;
      margin-top: -3px;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
      color: #6c757d;
      font-size: 1rem !important;
    }
    /* Select2 dropdown - lebih besar */
    .select2-dropdown {
      font-size: 1rem !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .select2-results__option {
      padding: 0.75rem 1rem !important;
      font-size: 1rem !important;
      line-height: 1.5 !important;
      transition: background-color 0.15s ease-in-out;
    }
    .select2-results__option--highlighted {
      background-color: #0d6efd !important;
      color: #fff !important;
    }
    .select2-results__option[aria-selected="true"] {
      background-color: #e7f1ff !important;
      color: #0d6efd !important;
    }
    /* Disabled state */
    .select2-container--default.select2-container--disabled .select2-selection--single {
      background-color: #e9ecef !important;
      cursor: not-allowed !important;
      opacity: 0.6;
    }
    /* ✅ Form controls styling sama seperti senarai-apc-admin.php */
    /* Pastikan semua select dalam modal tambah pengguna sama tinggi */
      #addUserModal .form-select,
      #userGroupModal .form-select,
      .form-select {
        min-height: 50px;
        padding: 0.875rem 1rem;
      font-size: 1rem;
      border: 2px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.2s ease;
      }
      #addUserModal .form-select,
      #addUserModal .form-control {
        min-height: 38px;
        height: 38px;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-width: 1px;
      }
      #addUserModal textarea.form-control {
        min-height: 38px;
        height: auto;
      }
      #addUserModal .big-select {
        font-size: 0.875rem !important;
        padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
        min-height: 38px !important;
        height: 38px !important;
        line-height: 1.5 !important;
      }
      #addUserModal .select2-container--default {
        font-size: 0.875rem !important;
      }
      #addUserModal .select2-dropdown {
        font-size: 0.875rem !important;
      }
      #addUserModal .select2-results__option {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        line-height: 1.45 !important;
      }
      /* Pastikan medan carian Select2 tidak runtuh/tersembunyi dalam modal. */
      #addUserModal .select2-container--open .select2-dropdown {
        z-index: 1065;
        border: 1px solid #dbe4f0 !important;
        background: #fff !important;
        overflow: hidden;
      }
      #addUserModal .select2-container--open .select2-search--dropdown {
        display: block !important;
        padding: 0.65rem !important;
        background: #f8fafc !important;
      }
      #addUserModal .select2-container--open .select2-search--dropdown .select2-search__field {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 100% !important;
        height: 40px !important;
        min-height: 40px !important;
        padding: 0.55rem 0.75rem !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 7px !important;
        outline: none !important;
        background: #fff !important;
        color: #0f172a !important;
        font-size: 0.875rem !important;
        line-height: 1.4 !important;
      }
      #addUserModal .select2-container--open .select2-search--dropdown .select2-search__field:focus {
        border-color: #6b8be4 !important;
        box-shadow: 0 0 0 3px rgba(107, 139, 228, 0.16) !important;
      }
      #addUserModal .select2-container--open .select2-results,
      #addUserModal .select2-container--open .select2-results__options {
        display: block !important;
      }
      #addUserModal .select2-container--open .select2-results__options {
        max-height: 250px !important;
        overflow-y: auto !important;
      }
      #addUserModal .form-select:focus,
      #userGroupModal .form-select:focus,
      .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    /* ✅ Textbox styling sama seperti senarai-apc-admin.php */
    .form-control {
      min-height: 50px;
      padding: 0.875rem 1rem;
      font-size: 1rem;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      transition: all 0.2s ease;
    }
    .form-control:focus {
      border-color: #86b7fe;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    /* Validation blink effect */
    #addUserModal .field-invalid {
      animation: fieldBlink 0.5s ease-in-out 3;
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    #addUserModal .select2-container.field-invalid .select2-selection--single {
      border-color: #dc3545 !important;
      animation: fieldBlink 0.5s ease-in-out 3;
      box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    @keyframes fieldBlink {
      0%, 100% { 
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
      }
      50% { 
        border-color: #ff6b7a !important;
        box-shadow: 0 0 0 0.3rem rgba(220, 53, 69, 0.4) !important;
      }
    }
      /* Section divider untuk layout yang lebih professional */
      #addUserModal .form-section {
        margin-bottom: 1rem;
        padding-bottom: 0.9rem;
        border-bottom: 2px solid #e9ecef;
      }
    #addUserModal .form-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
      #addUserModal .form-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #28a745;
        display: flex;
        align-items: center;
      }
      #addUserModal .form-section-title i {
        margin-right: 0.4rem;
        font-size: 0.95rem;
        color: #28a745;
      }
      /* Form field spacing */
      #addUserModal .mb-3 {
        margin-bottom: 1rem !important;
      }
    #addUserModal .mb-0 {
      margin-bottom: 0 !important;
    }
    /* Info card styling - professional look */
      #addUserModal .info-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.85rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      }
      #addUserModal .info-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 0.6rem;
        padding: 0.55rem;
        background-color: rgba(255,255,255,0.7);
        border-radius: 8px;
        transition: all 0.2s ease;
      }
    #addUserModal .info-item:hover {
      background-color: rgba(255,255,255,0.9);
      transform: translateX(2px);
    }
    #addUserModal .info-item:last-child {
      margin-bottom: 0;
    }
      #addUserModal .info-icon {
        color: #28a745;
        font-size: 1.15rem;
        margin-right: 0.7rem;
        margin-top: 0.125rem;
        flex-shrink: 0;
      }
    #addUserModal .info-content {
      flex: 1;
    }
      #addUserModal .info-label {
        font-size: 0.7rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        margin-bottom: 0.2rem;
      }
      #addUserModal .info-value {
        font-size: 0.9rem;
        color: #212529;
        font-weight: 600;
        line-height: 1.4;
      }
      #addUserModal .modal-content {
        border: none;
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
      }
      #addUserModal .modal-footer .btn {
        padding: 0.5rem 1.05rem;
        font-weight: 600;
      }
    #addUserModal #au_error {
      margin-top: 1rem;
      border-radius: 8px;
      border-left: 4px solid #dc3545;
    }
    .page-title-box{
      backdrop-filter: blur(10px);
    }
    .content-page .card{
      border-radius: 8px;
      border: 1px solid rgba(148,163,184,.14);
      box-shadow: 0 22px 48px rgba(15,23,42,.06);
      overflow: hidden;
    }
    .content-page .card > .card-body{
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.94));
    }
    #userAccessTabsContent .dataTables_wrapper .dt-top-left,
      #userAccessTabsContent .dataTables_wrapper .dt-top-right{
        padding-top: .2rem;
        padding-bottom: .35rem;
      }
    #userAccessTabsContent .dataTables_wrapper .dt-bottom-row .dt-info-left,
      #userAccessTabsContent .dataTables_wrapper .dt-bottom-row .dataTables_info{
        padding-left: .95rem !important;
      }
    #userAccessTabsContent .dataTables_wrapper .dt-group-filter,
    #userAccessTabsContent .dataTables_wrapper .dataTables_length select,
    #userAccessTabsContent .dataTables_wrapper .dataTables_filter input{
      box-shadow: 0 10px 24px rgba(15,23,42,.05) !important;
      border: 1px solid rgba(148,163,184,.24) !important;
      background: rgba(255,255,255,.98) !important;
      border-radius: 8px !important;
    }
    #userAccessTabsContent .dataTables_wrapper .dataTables_filter input:focus,
    #userAccessTabsContent .dataTables_wrapper .dataTables_length select:focus,
    #userAccessTabsContent .dataTables_wrapper .dt-group-filter:focus{
      border-color: rgba(59,130,246,.45) !important;
      box-shadow: 0 0 0 0.2rem rgba(59,130,246,.14), 0 12px 24px rgba(15,23,42,.06) !important;
    }
    #userAccessTabsContent .dataTables_wrapper .dt-top-right button:hover{
      transform: translateY(-1px);
      box-shadow: 0 14px 26px rgba(15,23,42,.08) !important;
    }
    #userAccessTabsContent .dataTables_wrapper .dt-top-right .btn{
      border-radius: 8px !important;
      box-shadow: 0 10px 24px rgba(15,23,42,.07);
    }
    #userAccessTabsContent .dataTables_wrapper .dt-top-right .btn-primary{
      box-shadow: 0 12px 26px rgba(37,99,235,.18);
    }
    #userDT thead th{
      padding: 0.9rem 0.85rem;
      border: 0;
      border-bottom: 1px solid rgba(148,163,184,.16) !important;
      background: linear-gradient(180deg, rgba(248,250,252,.98) 0%, rgba(241,245,249,.95) 100%);
      color: #334155;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
    }
    #userDT tbody tr{
      transition: background-color .18s ease, box-shadow .18s ease, transform .18s ease;
      height: 3rem;
    }
    #userDT tbody tr:hover{
      background: rgba(241,245,249,.88) !important;
      transform: none;
      box-shadow: inset 0 0 0 999px rgba(241,245,249,.3);
    }
    #userDT.table > tbody > tr:hover > *{
      background: rgba(241,245,249,.88) !important;
    }
    #userDT > tbody > tr:hover > td{
      background: rgba(241,245,249,.88) !important;
    }
    #userDT tbody td{
      height: 3rem;
      padding: .42rem .72rem !important;
      border-color: rgba(226,232,240,.9) !important;
      font-size: .86rem;
      background: rgba(255,255,255,.98) !important;
      line-height: 1.1;
      vertical-align: middle;
    }
    #userDT .btn,
    .icon-btn{
      border-radius: 8px !important;
      box-shadow: none;
    }
    #userDT td.col-group,
    #userDT td.col-akses,
    #userDT td.col-actions{
      white-space: nowrap;
    }
    #userDT .cell-inline{
      display: inline-flex;
      align-items: center;
      gap: .28rem;
      max-width: 100%;
      min-height: 1.4rem;
    }
    #userDT .group-chip,
    #userDT .access-chip{
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: .12rem .34rem;
      min-height: 1rem;
      font-size: .64rem;
      line-height: 1;
      font-weight: 600;
      vertical-align: middle;
      border-radius: 999px;
      border: 1px solid transparent;
    }
    #userDT .group-chip{
      max-width: 100%;
      color: #fff;
    }
    #userDT .access-chip{
      min-width: 4.1rem;
      background: rgba(15, 23, 42, 0.04);
      color: #334155;
      border-color: rgba(148, 163, 184, 0.22);
    }
    #userDT .access-chip.is-allowed{
      background: rgba(16, 185, 129, 0.12);
      color: #0f766e;
      border-color: rgba(20, 184, 166, 0.16);
    }
    #userDT .access-chip.is-blocked{
      background: rgba(239, 68, 68, 0.1);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, 0.14);
    }
    #userDT td.col-actions .btn{
      box-shadow: none !important;
      width: 1.75rem;
      height: 1.75rem;
      padding: 0;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    #userDT td.col-actions .btn + .btn{
      margin-left: .25rem !important;
    }
    #userDT .extra-roles-info{
      font-size: .68rem;
      vertical-align: middle;
      opacity: .68;
    }
    #userDT .extra-roles-info[data-has-extra="0"]{
      opacity: .45;
    }
    .user-access-table{
      width: 100%;
    }
    .user-access-table th,
    .user-access-table td{
      vertical-align: middle;
    }
    .user-access-table th.col-bil,
    .user-access-table td.col-bil{
      width: 5%;
      text-align: center;
    }
    .user-access-table th.col-nama,
    .user-access-table td.col-nama{
      width: 26%;
    }
    .user-access-table th.col-jabatan,
    .user-access-table td.col-jabatan{
      width: 21%;
    }
    .user-access-table th.col-jawatan,
    .user-access-table td.col-jawatan{
      width: 21%;
    }
    .user-access-table th.col-group,
    .user-access-table td.col-group{
      width: 9%;
    }
    .user-access-table th.col-akses,
    .user-access-table td.col-akses{
      width: 8%;
      text-align: center;
    }
    .user-access-table th.col-actions,
    .user-access-table td.col-actions{
      width: 10%;
      text-align: center;
    }
    .user-access-table--sixcol th.col-bil,
    .user-access-table--sixcol td.col-bil{
      width: 5%;
      text-align: center;
    }
    .user-access-table--sixcol th.col-nama,
    .user-access-table--sixcol td.col-nama{
      width: 30%;
      text-align: left;
    }
    .user-access-table--sixcol th.col-jabatan,
    .user-access-table--sixcol td.col-jabatan{
      width: 30%;
      text-align: left;
    }
    .user-access-table--sixcol th.col-group,
    .user-access-table--sixcol td.col-group{
      width: 15%;
      text-align: left;
    }
    .user-access-table--sixcol th.col-akses,
    .user-access-table--sixcol td.col-akses{
      width: 10%;
      text-align: left;
    }
    .user-access-table--sixcol th.col-actions,
    .user-access-table--sixcol td.col-actions{
      width: 10%;
      text-align: center;
    }
    .user-access-table thead th{
      background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
      color: #475569;
      font-size: .82rem;
      font-weight: 700;
      letter-spacing: .02em;
      text-transform: uppercase;
      padding: .82rem .78rem;
      border-bottom-color: rgba(148,163,184,.18);
    }
    .user-access-table tbody tr{
      height: 3rem;
      background: #fff;
    }
    .user-access-table tbody tr:hover,
    .user-access-table.table > tbody > tr:hover > *,
    .user-access-table > tbody > tr:hover > td{
      background: rgba(59,130,246,.045) !important;
    }
    .user-access-table tbody td{
      padding: .5rem .72rem;
      font-size: .84rem;
      line-height: 1.22;
      border-color: rgba(226,232,240,.78);
      background: transparent !important;
    }
    .user-access-table td.col-group,
    .user-access-table td.col-akses,
    .user-access-table td.col-actions{
      white-space: nowrap;
    }
    .user-access-table .cell-inline{
      display: inline-flex;
      align-items: center;
      gap: .28rem;
      max-width: 100%;
      min-height: 1.4rem;
    }
    .user-access-table .group-chip,
    .user-access-table .access-chip{
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: .12rem .34rem;
      min-height: 1rem;
      font-size: .64rem;
      line-height: 1;
      font-weight: 600;
      vertical-align: middle;
      border-radius: 999px;
      border: 1px solid transparent;
    }
    .user-access-table .group-chip{
      max-width: 100%;
      color: #fff;
    }
    .user-access-table .access-chip{
      min-width: 4.1rem;
      background: rgba(15, 23, 42, 0.04);
      color: #334155;
      border-color: rgba(148, 163, 184, 0.22);
    }
    .user-access-table .access-chip.is-allowed{
      background: rgba(16, 185, 129, 0.12);
      color: #0f766e;
      border-color: rgba(20, 184, 166, 0.16);
    }
    .user-access-table .access-chip.is-blocked{
      background: rgba(239, 68, 68, 0.1);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, 0.14);
    }
    .user-access-table td.col-actions .btn{
      box-shadow: none !important;
      width: 1.75rem;
      height: 1.75rem;
      padding: 0;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .user-access-table td.col-actions .btn + .btn{
      margin-left: .25rem !important;
    }
    .user-access-table .extra-roles-info{
      font-size: .68rem;
      vertical-align: middle;
      opacity: .68;
    }
    .user-access-table .extra-roles-info[data-has-extra="0"]{
      opacity: .45;
    }
    [data-bs-theme="dark"] .user-access-table thead th{
      background: rgba(30,41,59,.92);
      color: rgba(226,232,240,.94);
    }
    [data-bs-theme="dark"] .user-access-table tbody td{
      border-color: rgba(51,65,85,.72);
    }
    [data-bs-theme="dark"] .user-access-table tbody tr:hover,
    [data-bs-theme="dark"] .user-access-table.table > tbody > tr:hover > *,
    [data-bs-theme="dark"] .user-access-table > tbody > tr:hover > td{
      background: rgba(148,163,184,.1) !important;
    }
    #addUserModal .info-card,
    #userGroupModal .info-card{
      border-radius: 8px;
      box-shadow: 0 16px 34px rgba(15,23,42,.06);
      border-color: rgba(148,163,184,.16);
    }
    #addUserModal .info-item,
    #userGroupModal .info-item{
      border: 1px solid rgba(148,163,184,.12);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
    }
    [data-bs-theme="dark"] .content-page .card{
      border-color: rgba(255,255,255,.08);
      box-shadow: 0 22px 48px rgba(2,6,23,.22);
    }
    [data-bs-theme="dark"] .content-page .card > .card-body{
      background: linear-gradient(180deg, rgba(15,23,42,.97), rgba(2,6,23,.94));
    }
    [data-bs-theme="dark"] #userDT thead th{
      background: linear-gradient(180deg, rgba(30,41,59,.96) 0%, rgba(15,23,42,.94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148,163,184,.18) !important;
    }
    [data-bs-theme="dark"] #userDT tbody td{
      border-color: rgba(51,65,85,.95) !important;
      background: rgba(15,23,42,.92) !important;
    }
    [data-bs-theme="dark"] #userDT tbody tr:hover{
      background: rgba(30,41,59,.76) !important;
      box-shadow: inset 0 0 0 999px rgba(30,41,59,.18);
    }
    [data-bs-theme="dark"] #userDT.table > tbody > tr:hover > *{
      background: rgba(30,41,59,.76) !important;
    }
    [data-bs-theme="dark"] #userDT > tbody > tr:hover > td{
      background: rgba(30,41,59,.76) !important;
    }
    [data-bs-theme="dark"] #userDT tbody tr.row-group-adm-sa,
    [data-bs-theme="dark"] #userDT tbody tr.row-group-adm-hr,
    <?php foreach ($groupCssKeys as $index => $cssClass): ?>[data-bs-theme="dark"] #userDT tbody tr.<?= h($cssClass) ?><?= $index < count($groupCssKeys) - 1 ? ',' : '' ?>
    <?php endforeach; ?>{
      background-color: transparent !important;
    }
    [data-bs-theme="dark"] #userDT tbody tr.row-group-adm-sa td,
    [data-bs-theme="dark"] #userDT tbody tr.row-group-adm-hr td,
    <?php foreach ($groupCssKeys as $index => $cssClass): ?>[data-bs-theme="dark"] #userDT tbody tr.<?= h($cssClass) ?> td<?= $index < count($groupCssKeys) - 1 ? ',' : '' ?>
    <?php endforeach; ?>{
      background-color: transparent !important;
      background-image: none !important;
    }
    [data-bs-theme="dark"] #userDT_wrapper .dataTables_filter input,
    [data-bs-theme="dark"] #userDT_wrapper .dataTables_length select,
    [data-bs-theme="dark"] #userDT_wrapper #dtGroupFilter{
      background: rgba(15,23,42,.96) !important;
      border-color: rgba(148,163,184,.24) !important;
      color: #e2e8f0 !important;
    }
    [data-bs-theme="dark"] #userDT .access-chip{
      background: rgba(148, 163, 184, 0.08);
      color: #cbd5e1;
      border-color: rgba(148, 163, 184, 0.18);
    }
    [data-bs-theme="dark"] #userDT .access-chip.is-allowed{
      background: rgba(16, 185, 129, 0.16);
      color: #99f6e4;
      border-color: rgba(45, 212, 191, 0.2);
    }
    [data-bs-theme="dark"] #userDT .access-chip.is-blocked{
      background: rgba(239, 68, 68, 0.14);
      color: #fecaca;
      border-color: rgba(248, 113, 113, 0.18);
    }
    [data-bs-theme="dark"] .protected-account-badge{
      background: rgba(120,53,15,.36);
      color: #fcd34d;
      border-color: rgba(245,158,11,.28);
    }
    [data-bs-theme="dark"] .auto-provisioned-icon{
      background: rgba(8,47,73,.88);
      color: #7dd3fc;
      border-color: rgba(56,189,248,.3);
    }
    [data-bs-theme="dark"] #addUserModal .info-card,
    [data-bs-theme="dark"] #userGroupModal .info-card{
      background: linear-gradient(180deg, rgba(15,23,42,.96), rgba(15,23,42,.9));
      border-color: rgba(255,255,255,.08);
      box-shadow: 0 18px 38px rgba(2,6,23,.22);
    }
    [data-bs-theme="dark"] #addUserModal .au-modal-tabs {
      border-bottom-color: rgba(148, 163, 184, 0.18);
    }
    [data-bs-theme="dark"] #addUserModal .au-modal-tabs .nav-link {
      background: rgba(15, 23, 42, 0.78);
      border-color: rgba(148, 163, 184, 0.22);
      color: #dbe4f0;
    }
    [data-bs-theme="dark"] #addUserModal .au-modal-tabs .nav-link:hover {
      background: rgba(30, 41, 59, 0.92);
      color: #f8fafc;
    }
    [data-bs-theme="dark"] #addUserModal .au-modal-tabs .nav-link.active {
      border-color: rgba(32, 201, 151, 0.45);
      color: #fff;
    }

    /* User access workspace redesign */
    .user-list-page{
      --ul-accent: var(--ct-accent, var(--ct-primary));
      --ul-accent-start: var(--ct-primary-start, var(--ct-primary));
      --ul-accent-end: var(--ct-primary-end, var(--ct-primary));
      --ul-accent-soft: var(--ct-accent-soft, rgba(var(--ct-primary-rgb), .08));
      --ul-accent-border: var(--ct-accent-border, rgba(var(--ct-primary-rgb), .16));
      --ul-surface: rgba(255,255,255,.97);
      --ul-surface-soft: rgba(248,250,252,.9);
      --ul-border: rgba(148,163,184,.16);
      --ul-ink: #172033;
      --ul-muted: #64748b;
    }
    .user-list-page .page-title-box{
      min-height: 82px;
      padding: 1rem 1.15rem;
      border: 1px solid var(--ct-page-strip-border, var(--ul-border));
      border-radius: 12px;
      background: var(--ct-page-strip-bg, var(--ul-surface));
      box-shadow: 0 12px 28px rgba(15,23,42,.045);
    }
    .user-list-page .page-title-main{
      min-width: 0;
    }
    .user-list-page .page-title{
      display: flex;
      align-items: center;
      gap: .5rem;
      margin: 0;
      color: var(--ul-ink);
      font-size: 1.08rem;
      font-weight: 750;
    }
    .user-list-page .page-title > i{
      display: inline-flex;
      width: 36px;
      height: 36px;
      align-items: center;
      justify-content: center;
      border-radius: 9px;
      color: var(--ul-accent-end);
      background: var(--ul-accent-soft);
    }
    .user-list-page .page-title-subtitle{
      margin: .4rem 0 0 2.9rem;
      color: var(--ul-muted);
      font-size: .82rem;
      line-height: 1.5;
    }
    .user-list-page .user-list-workspace{
      overflow: hidden;
      border: 1px solid var(--ul-border);
      border-radius: 14px;
      background: var(--ul-surface);
      box-shadow: 0 18px 44px rgba(15,23,42,.06);
    }
    .user-list-page .user-list-tabs-wrap{
      padding: .7rem .75rem;
      border-bottom: 1px solid var(--ul-border);
      background: linear-gradient(180deg, var(--ul-surface), var(--ul-surface-soft));
    }
    .user-list-page #userAccessTabs{
      display: inline-flex;
      gap: .45rem;
      width: fit-content;
      max-width: 100%;
      margin: 0;
      padding: .35rem;
      border: 1px solid var(--ul-border);
      border-radius: 13px;
      background: var(--ul-surface-soft);
    }
    .user-list-page #userAccessTabs .nav-link{
      display: inline-flex;
      min-height: 42px;
      align-items: center;
      gap: .25rem;
      padding: .58rem .9rem;
      border: 1px solid transparent;
      border-radius: 10px;
      color: var(--ul-muted);
      font-weight: 700;
      white-space: nowrap;
      transition: .18s ease;
    }
    .user-list-page #userAccessTabs .nav-link i{
      display: inline-flex;
      width: 27px;
      height: 27px;
      align-items: center;
      justify-content: center;
      margin-left: -.3rem;
      border-radius: 8px;
      color: var(--ul-accent-end);
      background: var(--ul-accent-soft);
    }
    .user-list-page #userAccessTabs .nav-link:hover{
      color: var(--ul-accent-end);
      border-color: var(--ul-accent-border);
      background: var(--ul-surface);
      transform: translateY(-1px);
    }
    .user-list-page #userAccessTabs .nav-link.active{
      color: var(--ul-accent-end);
      border-color: var(--ul-accent-border);
      background: var(--ul-surface);
      box-shadow: 0 7px 18px rgba(15,23,42,.07), inset 0 -3px 0 var(--ul-accent-end);
    }
    .user-list-page #userAccessTabs .nav-link.active i{
      color: #fff;
      background: linear-gradient(135deg, var(--ul-accent-start), var(--ul-accent-end));
    }
    .user-list-page #userAccessTabsContent{
      padding: 1rem;
    }
    .user-list-page #userAccessTabsContent .dt-standard{
      overflow: hidden;
      border: 1px solid var(--ul-border);
      border-radius: 10px;
      box-shadow: none;
    }
    .user-list-page .user-access-table{
      box-shadow: none;
    }
    .user-list-page .user-access-table thead th{
      background: linear-gradient(180deg, var(--ul-accent-soft), var(--ul-surface-soft));
      color: var(--ul-muted);
    }
    .user-list-page #userAccessTabsContent .dataTables_wrapper .dt-top-left,
    .user-list-page #userAccessTabsContent .dataTables_wrapper .dt-top-right{
      min-height: 48px;
      align-items: center;
    }
    .user-list-page #userAccessTabsContent .dataTables_wrapper .form-control,
    .user-list-page #userAccessTabsContent .dataTables_wrapper .form-select,
    .user-list-page #userAccessTabsContent .dataTables_wrapper .btn{
      min-height: 38px;
      border-radius: 8px;
    }
    .user-list-page #userAccessTabsContent .dataTables_wrapper .btn-primary,
    .user-list-page #userAccessTabsContent .dataTables_wrapper .btn-success{
      border: 0;
      background: linear-gradient(135deg, var(--ul-accent-start), var(--ul-accent-end));
      box-shadow: 0 7px 16px rgba(var(--ct-primary-rgb), .15);
    }
    .user-list-page .modal-content{
      border: 1px solid var(--ul-border);
      border-radius: 13px;
      box-shadow: 0 28px 72px rgba(15,23,42,.22) !important;
    }
    .user-list-page #userGroupModal .modal-header,
    .user-list-page #roleExtraModal .modal-header,
    .user-list-page #addUserModal .modal-header{
      background: linear-gradient(135deg, var(--ul-accent-start), var(--ul-accent-end));
    }
    .user-list-page .form-section,
    .user-list-page .info-card{
      border-radius: 10px !important;
      box-shadow: none !important;
    }
    .user-list-page #addUserModal .au-modal-tabs{
      display: inline-flex;
      flex-wrap: nowrap;
      gap: .45rem;
      width: fit-content;
      max-width: 100%;
      margin: 0 0 1rem;
      padding: .35rem;
      overflow-x: auto;
      border: 1px solid var(--ul-border);
      border-radius: 13px;
      background: var(--ul-surface-soft);
    }
    .user-list-page #addUserModal .au-modal-tabs .nav-link{
      display: inline-flex;
      min-height: 42px;
      align-items: center;
      gap: .25rem;
      margin: 0;
      padding: .58rem .9rem;
      border: 1px solid transparent;
      border-radius: 10px;
      color: var(--ul-muted);
      background: transparent;
      box-shadow: none;
      font-weight: 700;
      white-space: nowrap;
      transition: .18s ease;
    }
    .user-list-page #addUserModal .au-modal-tabs .nav-link i{
      display: inline-flex;
      width: 27px;
      height: 27px;
      align-items: center;
      justify-content: center;
      margin-left: -.3rem;
      border-radius: 8px;
      color: var(--ul-accent-end);
      background: var(--ul-accent-soft);
    }
    .user-list-page #addUserModal .au-modal-tabs .nav-link:hover{
      color: var(--ul-accent-end);
      border-color: var(--ul-accent-border);
      background: var(--ul-surface);
      transform: translateY(-1px);
    }
    .user-list-page #addUserModal .au-modal-tabs .nav-link.active{
      color: var(--ul-accent-end);
      border-color: var(--ul-accent-border);
      background: var(--ul-surface);
      box-shadow: 0 7px 18px rgba(15,23,42,.07), inset 0 -3px 0 var(--ul-accent-end);
    }
    .user-list-page #addUserModal .au-modal-tabs .nav-link.active i{
      color: #fff;
      background: linear-gradient(135deg, var(--ul-accent-start), var(--ul-accent-end));
    }
    [data-bs-theme="dark"] .user-list-page{
      --ul-surface: rgba(20,27,39,.96);
      --ul-surface-soft: rgba(30,41,59,.72);
      --ul-border: rgba(148,163,184,.15);
      --ul-ink: #f1f5f9;
      --ul-muted: #9aa9bc;
    }
    @media (max-width: 767.98px){
      .user-list-page .page-title-box{
        align-items: flex-start !important;
        gap: .7rem;
      }
      .user-list-page .page-title-subtitle{
        margin-left: 0;
      }
      .user-list-page .user-list-tabs-wrap{
        overflow-x: auto;
      }
      .user-list-page #userAccessTabs{
        flex-wrap: nowrap;
      }
      .user-list-page #userAccessTabsContent{
        padding: .7rem;
      }
    }
  </style>
</head>

<body
  data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
  data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
  data-layout="vertical"
  data-sidebar-size="default"
  class="loading user-list-page">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">

        <!-- Tajuk + breadcrumb -->
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <div class="page-title-main">
                <h4 class="page-title"><i class="ri-user-settings-line me-1"></i> <?= h(__('userList_page_heading_main')) ?></h4>
                <p class="page-title-subtitle"><?= h(__('userList_page_intro')) ?></p>
              </div>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item">
                    <a href="dashboard.php">
                      <i class="ri-home-4-line align-middle me-1"></i> <?= __('breadcrumb_home') ?>
                    </a>
                  </li>
                  <li class="breadcrumb-item active"><?= __('userList_page_heading_main') ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <section class="user-list-workspace">
        <div class="user-list-tabs-wrap">
            <ul class="nav nav-tabs nav-bordered" id="userAccessTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link active"
                  id="tab-staff-access-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#tab-staff-access"
                  type="button"
                  role="tab"
                  aria-controls="tab-staff-access"
                  aria-selected="true">
                  <i class="ri-user-line me-1"></i> <?= h(__('userList_tab_staff')) ?>
                </button>
              </li>
              <?php if ($studentModeEnabled): ?>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="tab-student-access-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#tab-student-access"
                  type="button"
                  role="tab"
                  aria-controls="tab-student-access"
                  aria-selected="false">
                  <i class="ri-graduation-cap-line me-1"></i> <?= h(__('userList_tab_student')) ?>
                </button>
              </li>
              <?php endif; ?>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="tab-public-access-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#tab-public-access"
                  type="button"
                  role="tab"
                  aria-controls="tab-public-access"
                  aria-selected="false">
                  <i class="ri-user-star-line me-1"></i> <?= h(__('userList_tab_public')) ?>
                </button>
              </li>
            </ul>
        </div>

        <div class="tab-content" id="userAccessTabsContent">
          <div
            class="tab-pane fade show active"
            id="tab-staff-access"
            role="tabpanel"
            aria-labelledby="tab-staff-access-tab"
            tabindex="0">
            <?php if ($showGroupUiDebug): ?>
            <div class="row mb-2">
              <div class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-2">
                  <strong><?= h(__('userList_debug_group_ui_title')) ?></strong>
                  <span class="ms-2"><?= h(sprintf((string)__('userList_debug_group_ui_stats'), count($senaraiGroup), count($groupUiById), count($groupUiByCode), count($groupDynamicCssRules))) ?></span>
                  <div class="small mt-1"><?= str_replace('{query}', '<code>?debug_group_ui=1</code>', (string)__('userList_debug_group_ui_hint')) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php render_user_access_table(
              'userDT',
              $senaraiUserStaf,
              $userModel,
              $groupUiMaps,
              $currentUserId,
              $currentUserStafIDNormalized,
              $currentUserNoPekerjaNormalized,
              $isSuperAdmin,
              $dbMySQL,
              $profile,
              'staff',
              (string)__('userList_col_name_staffid'),
              (string)__('userList_col_department'),
              false
            ); ?>
          </div>

          <?php if ($studentModeEnabled): ?>
          <div
            class="tab-pane fade"
            id="tab-student-access"
            role="tabpanel"
            aria-labelledby="tab-student-access-tab"
            tabindex="0">
            <?php if ($showGroupUiDebug): ?>
            <div class="row mb-2">
              <div class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-2">
                  <strong><?= h(__('userList_debug_group_ui_title')) ?></strong>
                  <span class="ms-2"><?= h(sprintf((string)__('userList_debug_group_ui_stats'), count($senaraiGroup), count($groupUiById), count($groupUiByCode), count($groupDynamicCssRules))) ?></span>
                  <div class="small mt-1"><?= str_replace('{query}', '<code>?debug_group_ui=1</code>', (string)__('userList_debug_group_ui_hint')) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php render_user_access_table(
              'userDTStudent',
              $senaraiUserPelajar,
              $userModel,
              $groupUiMaps,
              $currentUserId,
              $currentUserStafIDNormalized,
              $currentUserNoPekerjaNormalized,
              $isSuperAdmin,
              $dbMySQL,
              $profile,
              'student',
              (string)__('userList_col_name_matric'),
              (string)__('userList_col_faculty'),
              false
            ); ?>
          </div>
          <?php endif; ?>

          <div
            class="tab-pane fade"
            id="tab-public-access"
            role="tabpanel"
            aria-labelledby="tab-public-access-tab"
            tabindex="0">
            <?php if ($showGroupUiDebug): ?>
            <div class="row mb-2">
              <div class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-2">
                  <strong><?= h(__('userList_debug_group_ui_title')) ?></strong>
                  <span class="ms-2"><?= h(sprintf((string)__('userList_debug_group_ui_stats'), count($senaraiGroup), count($groupUiById), count($groupUiByCode), count($groupDynamicCssRules))) ?></span>
                  <div class="small mt-1"><?= str_replace('{query}', '<code>?debug_group_ui=1</code>', (string)__('userList_debug_group_ui_hint')) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php render_user_access_table(
              'userDTPublic',
              $senaraiUserUmum,
              $userModel,
              $groupUiMaps,
              $currentUserId,
              $currentUserStafIDNormalized,
              $currentUserNoPekerjaNormalized,
              $isSuperAdmin,
              $dbMySQL,
              $profile,
              'public',
              (string)__('userList_col_name_login'),
              (string)__('userList_col_university'),
              false
            ); ?>
          </div>
        </div>
        </section>

      </div><!-- /.container-fluid -->
    </div><!-- /.content -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div><!-- /.content-page -->
</div><!-- /.wrapper -->

<?php include __DIR__ . '/../includes/script.php'; ?>

<!--
  vendor.min.js dimuat secara defer dan mengandungi jQuery. Muat Select2 secara defer
  selepas vendor supaya plugin melekat pada instance jQuery terakhir, bukan instance
  awal yang kemudiannya diganti oleh bundle vendor.
-->
<script src="<?= base_url('assets/vendor/select2/js/select2.full.min.js') ?>?v=<?= h($version) ?>" defer></script>

<!-- MODAL: Tukar Kumpulan -->
<div class="modal fade" id="userGroupModal" tabindex="-1" aria-hidden="true" aria-labelledby="userGroupTitle">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userGroupTitle">
          <i class="ri-user-settings-line me-2"></i> <?= __('userList_modal_title') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userList_modal_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form id="userGroupForm" autocomplete="off">
          <input type="hidden" id="ug_userID" value="">
          <input type="hidden" id="ug_nopekerja" value="">
          <input type="hidden" id="ug_scope" value="staff">

          <ul class="nav nav-tabs profile-tabs mb-3" id="ugTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="ug-tab-userinfo" data-bs-toggle="tab" data-bs-target="#ug-pane-userinfo" type="button" role="tab" aria-controls="ug-pane-userinfo" aria-selected="true">
                <?= __('userList_modal_section_user_info') ?>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="ug-tab-settings" data-bs-toggle="tab" data-bs-target="#ug-pane-settings" type="button" role="tab" aria-controls="ug-pane-settings" aria-selected="false">
                <?= __('userList_modal_section_settings') ?>
              </button>
            </li>
            <li class="nav-item" role="presentation" id="ug-tab-public-wrap">
              <button class="nav-link" id="ug-tab-public" data-bs-toggle="tab" data-bs-target="#ug-pane-public" type="button" role="tab" aria-controls="ug-pane-public" aria-selected="false">
                <?= __('userList_modal_section_public_info') ?>
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="ug-pane-userinfo" role="tabpanel" aria-labelledby="ug-tab-userinfo">
              <div class="form-section mb-0">
                <div class="form-section-title">
                  <i class="ri-user-line me-1"></i> <?= __('userList_modal_section_user_info') ?>
                </div>
                <div class="user-info-row">
                  <div class="avatar-container">
                    <img id="ug_avatar" src="" alt="<?= h(__('userList_avatar_alt')) ?>" class="user-avatar" onerror="this.src='<?= base_url('assets/images/no-image.jpg') ?>'">
                  </div>
                  <div class="info-card">
                    <div class="info-item">
                      <i class="ri-user-line info-icon"></i>
                      <div class="info-content">
                        <div class="info-label"><?= __('userList_modal_label_name') ?></div>
                        <div class="info-value" id="ug_nama"><?= __('userList_empty_value') ?></div>
                      </div>
                    </div>
                    <div class="info-item">
                      <i class="ri-building-line info-icon"></i>
                      <div class="info-content">
                        <div class="info-label"><?= __('userList_modal_label_department') ?></div>
                        <div class="info-value" id="ug_jabatan"><?= __('userList_empty_value') ?></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="ug-pane-settings" role="tabpanel" aria-labelledby="ug-tab-settings">
              <div class="form-section mb-0">
                <div class="form-section-title">
                  <i class="ri-settings-3-line me-1"></i> <?= __('userList_modal_section_settings') ?>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    <i class="ri-group-line"></i> <?= __('userList_modal_label_group') ?>
                  </label>
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="flex-grow-1">
                      <select class="form-select compact-select" id="ug_groupKod">
                        <option value=""><?= __('userList_group_filter_placeholder') ?></option>
                      </select>
                    </div>
                    <button type="button" class="btn btn-primary compact-btn" id="ug_addRoleBtn">
                      <i class="ri-add-line me-1"></i> <?= h(preg_replace('/^\+\s*/', '', __('userList_modal_add_role'))) ?>
                    </button>
                  </div>
                </div>
                <div class="mb-0">
                  <label class="form-label">
                    <i class="ri-shield-check-line"></i> <?= __('userList_modal_label_access') ?>
                  </label>
                  <select class="form-select compact-select" id="ug_flag">
                    <option value="1"><?= __('userList_access_granted') ?></option>
                    <option value="0"><?= __('userList_access_blocked') ?></option>
                  </select>
                </div>
                <div class="mt-3 d-none" id="ug_passwordSection">
                  <label class="form-label">
                    <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_reset_password') ?>
                  </label>
                  <div class="alert alert-warning py-2 px-3 mb-3 small">
                    <i class="ri-shield-keyhole-line me-1"></i> <?= __('userList_password_reset_forces_change') ?>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="ug_resetPassword" class="form-label">
                        <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password') ?>
                      </label>
                      <input type="password" class="form-control" id="ug_resetPassword" maxlength="100" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                      <label for="ug_resetPasswordConfirm" class="form-label">
                        <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password_confirm') ?>
                      </label>
                      <input type="password" class="form-control" id="ug_resetPasswordConfirm" maxlength="100" autocomplete="new-password">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="ug-pane-public" role="tabpanel" aria-labelledby="ug-tab-public">
              <div class="form-section d-none mb-0" id="ug_publicSection">
                <div class="form-section-title">
                  <i class="ri-user-star-line me-1"></i> <?= __('userList_modal_section_public_info') ?>
                </div>
                <div class="ug-public-grid">
                  <div class="ug-public-card">
                    <div class="ug-public-card-title">
                      <i class="ri-profile-line me-1"></i> Account Details
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="ug_publicName" class="form-label">
                          <i class="ri-user-3-line"></i> <?= __('userList_modal_label_public_name') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="ug_publicName" maxlength="150" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicNickname" class="form-label">
                          <i class="ri-user-smile-line"></i> <?= __('userList_modal_label_public_nickname') ?>
                        </label>
                        <input type="text" class="form-control" id="ug_publicNickname" maxlength="150" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicEmail" class="form-label">
                          <i class="ri-mail-line"></i> <?= __('userList_modal_label_public_email') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="ug_publicEmail" maxlength="150" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicPhone" class="form-label">
                          <i class="ri-smartphone-line"></i> <?= __('userList_modal_label_public_phone') ?>
                        </label>
                        <input type="text" class="form-control" id="ug_publicPhone" maxlength="30" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicUniversity" class="form-label">
                          <i class="ri-school-line"></i> <?= __('userList_modal_label_public_university') ?>
                        </label>
                        <input type="text" class="form-control" id="ug_publicUniversity" maxlength="150" autocomplete="off">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicNoKp" class="form-label">
                          <i class="ri-fingerprint-line"></i> <?= __('userList_modal_label_public_idno') ?>
                        </label>
                        <input type="text" class="form-control" id="ug_publicNoKp" maxlength="30" autocomplete="off">
                      </div>
                    </div>
                  </div>

                  <div class="ug-public-card">
                    <div class="ug-public-card-title">
                      <i class="ri-lock-2-line me-1"></i> Security
                    </div>
                    <div class="ug-public-hint">
                      <i class="ri-information-line me-1"></i> <?= __('userList_public_password_hint') ?>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="ug_publicPassword" class="form-label">
                          <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password') ?>
                        </label>
                        <input type="password" class="form-control" id="ug_publicPassword" maxlength="100" autocomplete="new-password">
                      </div>
                      <div class="col-md-6">
                        <label for="ug_publicPasswordConfirm" class="form-label">
                          <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password_confirm') ?>
                        </label>
                        <input type="password" class="form-control" id="ug_publicPasswordConfirm" maxlength="100" autocomplete="new-password">
                      </div>
                      <div class="col-12">
                        <div class="alert alert-warning py-2 px-3 mb-0 small">
                          <i class="ri-shield-keyhole-line me-1"></i> <?= __('userList_public_password_reset_forces_change') ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
        <div id="ug_error" class="modal-error alert alert-danger d-none mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= __('userList_modal_btn_close') ?>
        </button>
        <button type="button" class="btn btn-primary" id="ug_saveBtn">
          <i class="ri-save-3-line me-1"></i> <?= __('userList_modal_btn_save') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Peranan Tambahan -->
<div class="modal fade" id="roleExtraModal" tabindex="-1" aria-hidden="true" aria-labelledby="roleExtraTitle" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="roleExtraTitle">
          <i class="ri-shield-user-line me-1"></i> <?= __('userList_modal_extra_role_title') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userList_modal_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form id="roleExtraForm" autocomplete="off">
          <input type="hidden" id="re_userID" value="">
          <div class="text-muted small mb-2">
            <?= h(__('userList_primary_role_label')) ?>:
            <strong id="re_primaryRole"><?= __('userList_empty_value') ?></strong>
          </div>
          <div class="role-list" id="roleExtraList"></div>
        </form>
        <div id="roleExtraError" class="modal-error alert alert-danger d-none mt-3"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= __('userList_modal_btn_cancel') ?>
        </button>
        <button class="btn btn-primary" id="roleExtraSaveBtn">
          <i class="ri-save-3-line me-1"></i> <?= __('userList_modal_btn_save') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Tambah Pengguna -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true" aria-labelledby="addUserModalTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserModalTitle">
          <i class="ri-user-add-line me-2"></i> <?= __('userList_modal_add_title') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userList_modal_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form id="addUserForm" autocomplete="off">
          <ul class="nav nav-pills au-modal-tabs" id="auModalTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="au-info-tab" data-bs-toggle="tab" data-bs-target="#au-info-pane" type="button" role="tab" aria-controls="au-info-pane" aria-selected="true">
                <i class="ri-user-line me-1"></i> <span id="au_infoTabLabel"><?= __('userList_modal_section_staff_info') ?></span>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="au-settings-tab" data-bs-toggle="tab" data-bs-target="#au-settings-pane" type="button" role="tab" aria-controls="au-settings-pane" aria-selected="false">
                <i class="ri-settings-3-line me-1"></i> <?= __('userList_modal_section_settings') ?>
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active au-tab-pane" id="au-info-pane" role="tabpanel" aria-labelledby="au-info-tab" tabindex="0">
              <div class="form-section">
                <div class="form-section-title" id="au_sectionTitle">
                  <i class="ri-user-line me-1"></i> <?= __('userList_modal_section_staff_info') ?>
                </div>
                <div class="mb-3">
                  <div id="au_staffSelectWrap">
                    <label for="au_stafSelect" class="form-label" id="au_selectLabel">
                      <i class="ri-user-line"></i> <?= __('userList_modal_label_staff') ?> <span class="text-danger">*</span>
                    </label>
                    <select class="form-select js-staf-select" id="au_stafSelect" data-placeholder="<?= h(__('userList_modal_placeholder_select_staff')) ?>">
                      <option value=""></option>
                      <?php if (!empty($senaraiStaf)): ?>
                        <?php foreach ($senaraiStaf as $s): ?>
                          <?php
                            $nopekerja = trim((string)($s['nopekerja'] ?? ''));
                            $idpekerja = trim((string)($s['idpekerja'] ?? ''));
                            $nama = trim((string)($s['nama'] ?? ''));
                            $jawatan = trim((string)($s['jawatan'] ?? ''));
                            $jabatan = trim((string)($s['jabatan'] ?? ''));
                            
                            if ($nopekerja === '') continue;
                            
                            $nopekerjaNormalized = str_replace('-', '', $nopekerja);
                            $isDisabled = in_array($nopekerjaNormalized, $existingStafIDs, true);
                            
                            $displayText = $nama;
                            if ($nopekerja) {
                              $displayText .= ' (' . $nopekerja . ')';
                            }
                            if ($isDisabled) {
                              $displayText .= ' [' . __('userList_staff_already_exists') . ']';
                            }
                          ?>
                          <option
                            value="<?= h($nopekerja) ?>"
                            data-idpekerja="<?= h($idpekerja) ?>"
                            data-nama="<?= h($nama) ?>"
                            data-jawatan="<?= h($jawatan) ?>"
                            data-jabatan="<?= h($jabatan) ?>"
                            <?= $isDisabled ? 'disabled' : '' ?>
                          >
                            <?= h($displayText) ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>

                <div id="au_infoCard" class="info-card" style="display: block;">
                  <div class="info-item">
                    <i class="ri-building-line info-icon"></i>
                    <div class="info-content">
                      <div class="info-label" id="au_primaryInfoLabel"><?= __('userList_modal_label_department') ?></div>
                      <div class="info-value" id="au_jabatan"><?= __('userList_empty_value') ?></div>
                    </div>
                  </div>
                  <div class="info-item">
                    <i class="ri-briefcase-line info-icon"></i>
                    <div class="info-content">
                      <div class="info-label" id="au_secondaryInfoLabel"><?= __('userList_modal_label_position') ?></div>
                      <div class="info-value" id="au_jawatan"><?= __('userList_empty_value') ?></div>
                    </div>
                  </div>
                  <div class="info-item" id="au_extraInfo1Wrap" style="display:none;">
                    <i class="ri-graduation-cap-line info-icon"></i>
                    <div class="info-content">
                      <div class="info-label" id="au_extraInfo1Label"><?= __('userList_modal_label_level') ?></div>
                      <div class="info-value" id="au_extraInfo1"><?= __('userList_empty_value') ?></div>
                    </div>
                  </div>
                  <div class="info-item" id="au_extraInfo2Wrap" style="display:none;">
                    <i class="ri-shield-user-line info-icon"></i>
                    <div class="info-content">
                      <div class="info-label" id="au_extraInfo2Label"><?= __('userList_modal_label_status_category') ?></div>
                      <div class="info-value" id="au_extraInfo2"><?= __('userList_empty_value') ?></div>
                    </div>
                  </div>
                </div>

                <div id="au_publicFormSection" class="row g-3 d-none">
                  <div class="col-md-6">
                    <label for="au_publicName" class="form-label">
                      <i class="ri-user-3-line"></i> <?= __('userList_modal_label_public_name') ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="au_publicName" maxlength="150" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicNickname" class="form-label">
                      <i class="ri-user-smile-line"></i> <?= __('userList_modal_label_public_nickname') ?>
                    </label>
                    <input type="text" class="form-control" id="au_publicNickname" maxlength="150" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicEmail" class="form-label">
                      <i class="ri-mail-line"></i> <?= __('userList_modal_label_public_email') ?> <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="au_publicEmail" maxlength="150" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicPhone" class="form-label">
                      <i class="ri-smartphone-line"></i> <?= __('userList_modal_label_public_phone') ?>
                    </label>
                    <input type="text" class="form-control" id="au_publicPhone" maxlength="30" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicUniversity" class="form-label">
                      <i class="ri-school-line"></i> <?= __('userList_modal_label_public_university') ?>
                    </label>
                    <input type="text" class="form-control" id="au_publicUniversity" maxlength="150" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicNoKp" class="form-label">
                      <i class="ri-fingerprint-line"></i> <?= __('userList_modal_label_public_idno') ?>
                    </label>
                    <input type="text" class="form-control" id="au_publicNoKp" maxlength="30" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicPassword" class="form-label">
                      <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password') ?> <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" id="au_publicPassword" maxlength="100" autocomplete="new-password">
                  </div>
                  <div class="col-md-6">
                    <label for="au_publicPasswordConfirm" class="form-label">
                      <i class="ri-lock-password-line"></i> <?= __('userList_modal_label_public_password_confirm') ?> <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" id="au_publicPasswordConfirm" maxlength="100" autocomplete="new-password">
                  </div>
                  <div class="col-12">
                    <div class="alert alert-warning py-2 px-3 mb-0 small">
                      <i class="ri-shield-keyhole-line me-1"></i> <?= __('userList_public_password_reset_forces_change') ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade au-tab-pane" id="au-settings-pane" role="tabpanel" aria-labelledby="au-settings-tab" tabindex="0">
              <div class="form-section">
                <div class="form-section-title">
                  <i class="ri-settings-3-line me-1"></i> <?= __('userList_modal_section_settings') ?>
                </div>
                <div class="mb-3">
                  <label for="au_groupKod" class="form-label">
                    <i class="ri-group-line"></i> <?= __('userList_modal_label_group') ?> <span class="text-danger">*</span>
                  </label>
                  <select class="form-select big-select" id="au_groupKod" required>
                    <option value=""><?= __('userList_modal_placeholder_select_group') ?></option>
                    <?php foreach ($assignableGroup as $g): ?>
                      <option value="<?= h((string)($g['f_groupID'] ?? '')) ?>"><?= h($g['f_groupName']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="mb-0">
                  <label for="au_flag" class="form-label">
                    <i class="ri-shield-check-line"></i> <?= __('userList_modal_label_access') ?> <span class="text-danger">*</span>
                  </label>
                  <select class="form-select" id="au_flag" required>
                    <option value="1"><?= __('userList_access_granted') ?></option>
                    <option value="0"><?= __('userList_access_blocked') ?></option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </form>
        <div id="au_error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= __('userList_modal_btn_cancel') ?>
        </button>
        <button type="button" class="btn btn-success" id="au_saveBtn">
          <i class="ri-save-3-line me-1"></i> <?= __('userList_modal_btn_save') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const hasDT = () => !!(window.jQuery && jQuery.fn && jQuery.fn.DataTable);
  const CSRF  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const table = document;

  // ==================== CONFIGURATION CONSTANTS ====================
  const CONFIG = {
    HIGHLIGHT_DURATION: 20000,        // 20 seconds
    ANIMATION_DELAY: 300,             // 300ms
    SELECT2_RETRY_DELAY: 50,         // 50ms
    SELECT2_MAX_RETRIES: 100,        // Max 5 seconds
    RATE_LIMIT_DELAY: 1000,          // 1 second between requests
    RETRY_MAX_ATTEMPTS: 3,            // Max retry attempts
    RETRY_BASE_DELAY: 1000,           // Base delay for exponential backoff
    DEBUG: false,                     // Debug mode
    STUDENT_MODE_ENABLED: <?= $studentModeEnabled ? 'true' : 'false' ?>,
    GROUP_UI_BY_ID: <?= json_encode($groupUiById, JSON_UNESCAPED_UNICODE) ?>,
    GROUP_UI_BY_CODE: <?= json_encode($groupUiByCode, JSON_UNESCAPED_UNICODE) ?>,
    GROUPS_BY_SCOPE: {
      staff: <?= json_encode(array_map(static fn($g) => [
        'id' => (int)($g['f_groupID'] ?? 0),
        'kod' => (string)($g['f_groupKod'] ?? ''),
        'nama' => (string)($g['f_groupName'] ?? ''),
        'categoryUser' => (string)($g['f_categoryUser'] ?? ''),
      ], $assignableGroupStaf), JSON_UNESCAPED_UNICODE) ?>,
      student: <?= json_encode(array_map(static fn($g) => [
        'id' => (int)($g['f_groupID'] ?? 0),
        'kod' => (string)($g['f_groupKod'] ?? ''),
        'nama' => (string)($g['f_groupName'] ?? ''),
        'categoryUser' => (string)($g['f_categoryUser'] ?? ''),
      ], $assignableGroupPelajar), JSON_UNESCAPED_UNICODE) ?>,
      public: <?= json_encode(array_map(static fn($g) => [
        'id' => (int)($g['f_groupID'] ?? 0),
        'kod' => (string)($g['f_groupKod'] ?? ''),
        'nama' => (string)($g['f_groupName'] ?? ''),
        'categoryUser' => (string)($g['f_categoryUser'] ?? ''),
      ], $assignableGroupUmum), JSON_UNESCAPED_UNICODE) ?>
    },
    COLORS: {
      GROUP_ADM_SA: '#ffe8e8',
      GROUP_ADM_HR: '#fffef0',
      HIGHLIGHT_SUCCESS: '#d4edda'
    }
  };

  // Global variable untuk DataTable instance
  let dtInstance = null;
  
  // Request cancellation controller
  let currentRequestController = null;
  
  // Rate limiting tracker
  const rateLimitTracker = new Map();

  // Permission check
  const currentUserGroup = '<?= h($currentUserGroup) ?>';
  const currentUserIdentity = {
    userID: '<?= h((string)$currentUserId) ?>',
    stafID: '<?= h($currentUserStafIDNormalized) ?>',
    nopekerja: '<?= h($currentUserNoPekerjaNormalized) ?>'
  };
  const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
  const canAddUsers = <?= $canAddUsers ? 'true' : 'false' ?>;
  const canEditUsers = <?= $canEditUsers ? 'true' : 'false' ?>;
  const canDeleteUsers = <?= $canDeleteUsers ? 'true' : 'false' ?>;
  const protectedStaffIds = <?= json_encode(array_values(defined('PRESTASI_PROTECTED_STAFF_IDS') && is_array(PRESTASI_PROTECTED_STAFF_IDS) ? PRESTASI_PROTECTED_STAFF_IDS : []), JSON_UNESCAPED_UNICODE) ?>;

  // ==================== HELPER FUNCTIONS ====================
  
  /**
   * Sanitize error messages untuk prevent exposing system details
   */
  function sanitizeError(error) {
    if (!error) return '<?= h(__('userList_err_unknown')) ?>';
    const msg = error.message || error.toString() || '<?= h(__('userList_err_unknown')) ?>';
    // Remove technical details
    return msg
      .replace(/in \/.*?\.php:\d+/g, '')
      .replace(/SQLSTATE\[.*?\]/g, '')
      .replace(/PDOException:/g, '')
      .replace(/Exception:/g, '')
      .substring(0, 200); // Limit length
  }

  /**
   * Check permission dengan user-friendly error
   */
  /**
   * Rate limiting untuk prevent spam clicks
   */
  function checkRateLimit(key, delay = CONFIG.RATE_LIMIT_DELAY) {
    const now = Date.now();
    const lastCall = rateLimitTracker.get(key) || 0;
    
    if (now - lastCall < delay) {
      return false;
    }
    
    rateLimitTracker.set(key, now);
    return true;
  }

  function fireSwal(options) {
    if (!window.Swal) {
      return Promise.resolve(null);
    }
    const config = (options && typeof options === 'object') ? { ...options } : {};
    delete config.timer;
    delete config.timerProgressBar;
    return Swal.fire({
      allowOutsideClick: false,
      allowEscapeKey: false,
      ...config
    });
  }

  /**
   * Create rate-limited handler
   */
  function createRateLimitedHandler(handler, delay = CONFIG.RATE_LIMIT_DELAY) {
    return async function(...args) {
      const handlerKey = handler.name || 'anonymous';
      if (!checkRateLimit(handlerKey, delay)) {
        await fireSwal({
          icon: 'warning',
          title: '<?= h(__('userList_rate_limit_title')) ?>',
          text: '<?= h(__('userList_rate_limit_text')) ?>',
          timer: 2000,
          timerProgressBar: true,
          confirmButtonText: '<?= h(__('userList_btn_ok')) ?>'
        });
        return;
      }
      return handler.apply(this, args);
    };
  }

  /**
   * Input validation functions
   */
  function validateStafID(stafID) {
    if (!stafID || stafID.trim() === '') return false;
    // Format: XXXX-XX atau 6 digits
    const normalized = stafID.replace(/-/g, '');
    return /^\d{6}$/.test(normalized);
  }

    function validateGroupId(groupId) {
      if (groupId === null || groupId === undefined) return false;
      const n = parseInt(String(groupId), 10);
      return Number.isFinite(n) && n > 0;
    }

    function validateEmailAddress(email) {
      const normalized = String(email || '').trim();
      if (normalized === '') return false;
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized);
    }

  function isCurrentLoggedInUserTarget(userID, stafID, nopekerja = '') {
    const normalize = (value) => String(value || '').replace(/-/g, '').trim();
    const targetUserID = String(userID || '').trim();
    if (currentUserIdentity.userID && targetUserID && currentUserIdentity.userID === targetUserID) return true;
    if (currentUserIdentity.stafID && normalize(stafID) && currentUserIdentity.stafID === normalize(stafID)) return true;
    if (currentUserIdentity.nopekerja && normalize(nopekerja) && currentUserIdentity.nopekerja === normalize(nopekerja)) return true;
    return false;
  }

  function isProtectedStaffAccountClient(stafID) {
    const normalize = (value) => String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
    const target = normalize(stafID);
    if (!target) return false;
    return protectedStaffIds.some((candidate) => normalize(candidate) === target);
  }

  function canSelfManageProtectedStaffAccountClient(stafID) {
    const normalize = (value) => String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
    const target = normalize(stafID);
    return !!target && isProtectedStaffAccountClient(stafID) && currentUserIdentity.stafID === target;
  }

  /**
   * Fetch with retry mechanism (exponential backoff)
   */
  async function fetchWithRetry(url, options = {}, maxRetries = CONFIG.RETRY_MAX_ATTEMPTS) {
    for (let i = 0; i < maxRetries; i++) {
      try {
        const response = await fetch(url, options);
        if (response.ok) return response;
        
        // Retry on 5xx errors only
        if (i < maxRetries - 1 && response.status >= 500) {
          const delay = Math.pow(2, i) * CONFIG.RETRY_BASE_DELAY; // 1s, 2s, 4s
          await new Promise(resolve => setTimeout(resolve, delay));
          continue;
        }

        return response;
      } catch (e) {
        if (i === maxRetries - 1) throw e;
        // Network errors - retry with backoff
        if (e.name !== 'AbortError') {
          const delay = Math.pow(2, i) * CONFIG.RETRY_BASE_DELAY;
          await new Promise(resolve => setTimeout(resolve, delay));
        } else {
          throw e; // Don't retry aborted requests
        }
      }
    }
  }

  /**
   * Loading overlay management
   */
  function showLoading(message = '<?= h(__('userList_processing')) ?>') {
    hideLoading();
    window.__userListLoaderToken = message;
  }

  function hideLoading() {
    window.__userListLoaderToken = null;
  }

  function showImpersonationBoxLoader(message = '<?= h(__('impersonation_loading_start') ?: 'Preparing View As...') ?>') {
    if (window.showImpersonationBoxLoader) {
      window.showImpersonationBoxLoader(message);
      return;
    }
    showLoading(message);
  }

  function hideImpersonationBoxLoader() {
    if (window.hideImpersonationBoxLoader) {
      window.hideImpersonationBoxLoader();
      return;
    }
    hideLoading();
  }

  // Select2 loading is handled inline where needed; remove unused helper to keep bundle small.

  /**
   * Get badge class berdasarkan group ID
   */
  function normalizeGroupCode(code) {
    return String(code || '').toUpperCase().replace(/[^A-Z0-9]+/g, '');
  }

  function getGroupStyle(groupId, groupKod = '') {
    const idKey = String(parseInt(groupId || 0, 10) || 0);
    const codeKey = normalizeGroupCode(groupKod);
    const style = CONFIG.GROUP_UI_BY_ID[idKey] || (codeKey !== '' ? CONFIG.GROUP_UI_BY_CODE[codeKey] : null) || {};
    return {
      badgeClass: String(style.badgeClass || 'bg-secondary').trim() || 'bg-secondary',
      rowClass: String(style.rowClass || '').trim(),
      rowColor: String(style.rowColor || '').trim()
    };
  }

  function getBadgeClass(groupId, groupKod = '') {
    return getGroupStyle(groupId, groupKod).badgeClass;
  }

  function getBadgeInlineStyle(groupId, groupKod = '') {
    const style = getGroupStyle(groupId, groupKod);
    if (!style.rowColor) return '';
    return `background-color:${style.rowColor};color:${getReadableTextColor(style.rowColor)};`;
  }

  function getReadableTextColor(color) {
    const value = String(color || '').trim();
    const match = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!match) return '#ffffff';
    let hex = match[1];
    if (hex.length === 3) {
      hex = hex.split('').map((ch) => ch + ch).join('');
    }
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);
    const luminance = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    return luminance >= 160 ? '#1e293b' : '#ffffff';
  }

  /**
   * Get row class berdasarkan group ID
   */
  function getRowClass(groupId, groupKod = '') {
    return getGroupStyle(groupId, groupKod).rowClass;
  }

  function isValidCssColor(value) {
    const v = String(value || '').trim();
    if (!v) return false;
    return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v) || /^[a-zA-Z]+$/.test(v);
  }

  function toSoftRowBg(color) {
    const v = String(color || '').trim();
    const m3 = /^#([0-9a-f]{3})$/i.exec(v);
    if (m3) {
      const h = m3[1];
      const r = parseInt(h[0] + h[0], 16);
      const g = parseInt(h[1] + h[1], 16);
      const b = parseInt(h[2] + h[2], 16);
      return `rgba(${r}, ${g}, ${b}, 0.18)`;
    }
    const m6 = /^#([0-9a-f]{6})$/i.exec(v);
    if (m6) {
      const h = m6[1];
      const r = parseInt(h.slice(0, 2), 16);
      const g = parseInt(h.slice(2, 4), 16);
      const b = parseInt(h.slice(4, 6), 16);
      return `rgba(${r}, ${g}, ${b}, 0.18)`;
    }
    return v;
  }

  function applyRowClass($row) {
    const groupId = parseInt($row.attr('data-group-id') || '0', 10);
    const groupKod = String($row.attr('data-group-kod') || '');
    const mapStyle = getGroupStyle(groupId, groupKod);
    const nextClass = String(mapStyle.rowClass || '').trim();
    const oldClass = String($row.attr('data-row-class') || '').trim();
    if (oldClass) {
      $row.removeClass(oldClass);
    }
    const finalClass = nextClass || oldClass;
    if (finalClass) $row.addClass(finalClass);
    const trEl = $row.get(0);
    if (trEl && trEl.style) {
      trEl.style.removeProperty('background-color');
    }
    $row.find('td').each(function() {
      if (this && this.style) {
        this.style.removeProperty('background-color');
        this.style.removeProperty('background-image');
      }
    });
    $row.attr('data-row-class', finalClass);
  }

  /**
   * Render extra roles tooltip on info icon
   */
  function renderExtraRolesInfo(iconEl, roles) {
    if (!iconEl) return;
    const list = Array.isArray(roles) ? roles : [];
      const title = list.length ? list.join(', ') : '<?= h(__('userList_role_none')) ?>';
    iconEl.setAttribute('data-bs-toggle', 'tooltip');
    iconEl.setAttribute('data-bs-placement', 'top');
    iconEl.setAttribute('title', title);
  }

  /**
   * Init tooltips safely
   */
  function initTooltips(root = document) {
    if (!window.bootstrap || !bootstrap.Tooltip) return;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      try { bootstrap.Tooltip.getInstance(el)?.dispose(); } catch(e) {}
      new bootstrap.Tooltip(el, {
        customClass: 'userlist-cell-tooltip'
      });
    });
  }

  /**
   * Track event untuk analytics/debugging
   */
  function trackEvent(eventName, data = {}) {
    if (CONFIG.DEBUG) {
      console.log('[Event]', eventName, data);
    }
    // Send to server for audit (optional, non-blocking)
    try {
      fetch('<?= base_url('ajax/track-event.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          event: eventName, 
          data, 
          timestamp: Date.now(),
          userGroup: currentUserGroup
        })
      }).catch(() => {}); // Ignore errors
    } catch (e) {
      // Ignore tracking errors
    }
  }

  /**
   * Update user row in-place (optimized)
   */
  function updateUserRow(userID, newData) {
    const $row = $(`.user-access-table tbody tr[data-user-id="${userID}"]`).first();
    if ($row.length === 0) {
      // Row not visible, trigger full reload
      return reloadUserTable(userID);
    }
    
    // Update row attributes
    if (newData.groupID) {
      $row.attr('data-group-id', newData.groupID);
      const rowGroupKod = (newData.groupKod !== undefined) ? newData.groupKod : $row.attr('data-group-kod');
      $row.attr('data-group-kod', rowGroupKod || '');
      applyRowClass($row);
    }
    if (newData.groupKod) {
      $row.attr('data-group-kod', newData.groupKod);
      applyRowClass($row);
    }
    if (Array.isArray(newData.extraRoles)) {
      const listText = newData.extraRoles.join(', ');
      $row.attr('data-extra-roles', listText);
      $row.attr('data-extra-count', String(newData.extraRoles.length));
      const $info = $row.find('.extra-roles-info');
      $info.attr('data-has-extra', newData.extraRoles.length > 0 ? '1' : '0');
      renderExtraRolesInfo($info[0], newData.extraRoles);
      initTooltips($info[0] || document);
    }
    if (newData.flag !== undefined) {
      $row.attr('data-flag', newData.flag);
    }
    
    // Update group badge
    if (newData.groupID && newData.groupName) {
      const $badge = $row.find('.col-group .group-chip');
      const groupKod = newData.groupKod || $row.attr('data-group-kod') || '';
      const badgeInlineStyle = getBadgeInlineStyle(newData.groupID, groupKod);
      $badge.text(newData.groupName);
      if (badgeInlineStyle) {
        $badge.attr('style', badgeInlineStyle);
      } else {
        $badge.removeAttr('style');
      }
    }
    
    // Update access badge
    if (newData.flag !== undefined) {
      const $accessBadge = $row.find('.col-akses .access-chip');
      if (newData.flag == 1) {
        $accessBadge
          .removeClass('is-blocked')
          .addClass('is-allowed')
          .text('<?= h(__('userList_access_granted')) ?>');
      } else {
        $accessBadge
          .removeClass('is-allowed')
          .addClass('is-blocked')
          .text('<?= h(__('userList_access_blocked')) ?>');
      }
    }
    
    // Update button data attributes if needed
    if (canEditUsers) {
      if (newData.name !== undefined || newData.loginID !== undefined || newData.email !== undefined) {
        const currentName = String(newData.name !== undefined ? newData.name : ($row.find('.btn-edit-group').attr('data-nama') || ''));
        const currentLoginID = String(newData.loginID !== undefined ? newData.loginID : ($row.find('.btn-edit-group').attr('data-loginid') || ''));
        const currentStafID = String($row.find('.btn-edit-group').attr('data-stafid') || '');
        const scope = String($row.find('.btn-edit-group').attr('data-scope') || '').toLowerCase();
        const currentDisplayId = scope === 'public' ? (currentLoginID || currentStafID) : currentStafID;
        const nameText = currentName + (currentDisplayId ? (' (' + currentDisplayId + ')') : '');
        const $nameSpan = $row.find('.col-nama .cell-tooltip-text');
        $nameSpan.text(nameText).attr('title', nameText);
        $row.find('.btn-edit-group')
          .attr('data-nama', currentName)
          .attr('data-loginid', currentLoginID)
          .attr('data-displayid', currentDisplayId);
        $row.find('.btn-delete-user').attr('data-displayid', currentDisplayId);
      }
      if (newData.nickname !== undefined) {
        $row.find('.btn-edit-group').attr('data-nickname', newData.nickname);
      }
      if (newData.email !== undefined) {
        $row.find('.btn-edit-group').attr('data-email', newData.email);
      }
      if (newData.phone !== undefined) {
        $row.find('.btn-edit-group').attr('data-phone', newData.phone);
      }
      if (newData.university !== undefined) {
        $row.find('.btn-edit-group').attr('data-university', newData.university);
      }
      if (newData.nokp !== undefined) {
        $row.find('.btn-edit-group').attr('data-nokp', newData.nokp);
      }
      if (newData.jabatan !== undefined) {
        const jab = String(newData.jabatan || '');
        const $jabatanSpan = $row.find('.col-jabatan .cell-tooltip-text');
        $jabatanSpan.text(jab).attr('title', jab);
        $row.find('.btn-edit-group').attr('data-jabatan', jab);
      }
      if (newData.groupID) {
        $row.find('.btn-edit-group').attr('data-group-id', newData.groupID);
      }
      if (newData.groupKod) {
        $row.find('.btn-edit-group').attr('data-group-kod', newData.groupKod);
        if (newData.groupName) {
          $row.find('.btn-edit-group').attr('data-group-name', newData.groupName);
        }
      }
    }
    
    // Highlight row
    $row.addClass('row-updated-highlight');
    setTimeout(() => {
      $row.removeClass('row-updated-highlight');
    }, CONFIG.HIGHLIGHT_DURATION);
    
    // Scroll to row if not visible
    const rowOffset = $row.offset();
    if (rowOffset) {
      const windowTop = $(window).scrollTop();
      const windowBottom = windowTop + $(window).height();
      const rowTop = rowOffset.top;
      const rowBottom = rowTop + $row.outerHeight();
      
      if (rowTop < windowTop || rowBottom > windowBottom) {
        $('html, body').animate({
          scrollTop: rowTop - 100
        }, 500);
      }
    }
  }

  /**
   * Build a <tr> DOM node from a structured row object returned by server.
   * This avoids injecting raw HTML from server and improves XSS safety.
   */
  function buildRowFromData(r) {
    // Normalise possible server keys
    const userID = String(r.f_userID || r.userID || r.id || '');
    const nama = String(r.f_nama || r.nama || r.name || '');
    const loginID = String(r.f_loginID || r.loginID || r.login_id || '');
    const stafID = String(r.f_stafID || r.stafID || r.staf_id || '');
    const nickname = String(r.f_nickname || r.nickname || '');
    const email = String(r.f_email || r.email || '');
    const phone = String(r.f_handphone || r.phone || '');
    const university = String(r.f_namajabatan || r.university || r.jabatan || r.department || '');
    const nokp = String(r.f_nokp || r.nokp || '');
    const categoryUser = String(r.f_categoryUser || r.categoryUser || r.user_category || '').trim().toUpperCase();
    const jabatan = university;
    const jawatan = String(r.f_jawatan || r.jawatan || r.position || '');
    const gId  = parseInt(r.f_groupID || r.groupID || r.group_id || 0, 10);
    const gKod = String(r.f_groupKod || r.groupKod || r.group_kod || r.group || '');
    const gName = String(r.f_groupName || r.groupName || r.group_name || gKod);
    const explicitBadgeClass = String(r.f_badge_class || r.badgeClass || '').trim();
    const explicitRowClass = String(r.f_row_class || r.rowClass || '').trim();
    const explicitRowColor = String(r.f_row_color || r.rowColor || '').trim();
    const extraRoles = Array.isArray(r.extra_roles) ? r.extra_roles : (Array.isArray(r.extraRoles) ? r.extraRoles : []);
    const flag = (typeof r.f_flag !== 'undefined') ? r.f_flag : (typeof r.flag !== 'undefined' ? r.flag : 1);
    const nopekerja = String(r.f_nopekerja || r.nopekerja || '');
    const avatarUrl = String(r.avatarUrl || r.avatar || '');
    const isProtectedAccount = (typeof r.is_protected_account !== 'undefined')
      ? !!r.is_protected_account
      : isProtectedStaffAccountClient(stafID);
    const canEditGroup = (typeof r.can_edit_group !== 'undefined')
      ? !!r.can_edit_group
      : ((typeof r.canEditGroup !== 'undefined') ? !!r.canEditGroup : canEditUsers);
    const canDeleteUser = (typeof r.can_delete_user !== 'undefined')
      ? !!r.can_delete_user
      : ((typeof r.canDeleteUser !== 'undefined')
        ? !!r.canDeleteUser
        : (!isCurrentLoggedInUserTarget(userID, stafID, nopekerja) && !isProtectedAccount));
    const canViewAsUser = (typeof r.can_view_as_user !== 'undefined')
      ? !!r.can_view_as_user
      : (!!isSuperAdmin && !isCurrentLoggedInUserTarget(userID, stafID, nopekerja) && !isProtectedAccount && String(gKod).trim().toUpperCase() !== 'ADM-SA' && parseInt(flag, 10) === 1 && loginID !== '');

    // Create row element using jQuery to avoid unsafe innerHTML with server HTML
    const $tr = $('<tr>')
      .attr('data-user-id', userID)
      .attr('data-group-id', String(gId || ''))
      .attr('data-group-kod', gKod)
      .attr('data-row-color', explicitRowColor || getGroupStyle(gId, gKod).rowColor || '')
      .attr('data-row-class', explicitRowClass || getRowClass(gId, gKod))
      .attr('data-flag', String(flag))
      .attr('data-extra-count', String(extraRoles.length))
      .attr('data-extra-roles', extraRoles.join(', '))
      .addClass(explicitRowClass || getRowClass(gId, gKod));

    // Column: bil (filled by DataTable rowCallback)
    $tr.append($('<td>').addClass('col-bil'));

    // Column: nama (with stafID)
    const visibleIdentifier = categoryUser === 'UMUM' ? (loginID || stafID) : stafID;
    const nameText = nama + (visibleIdentifier ? (' (' + visibleIdentifier + ')') : '');
    const $nameShell = $('<div>').addClass('user-name-shell');
    $nameShell.append(
      $('<span>')
        .addClass('truncate-1line cell-tooltip-text')
        .attr('data-bs-toggle', 'tooltip')
        .attr('data-bs-placement', 'top')
        .attr('title', nameText)
        .text(nameText)
    );
    const isAutoProvisioned = Number(r.f_isAutoProvisioned || r.is_auto_provisioned || 0) === 1;
    const identitySource = String(r.f_identitySource || r.identitySource || '').trim().toUpperCase();
    const isSsoIdentity = identitySource === 'SSO';
    const showSsoIndicator = isAutoProvisioned || isSsoIdentity;
    if (showSsoIndicator || isProtectedAccount) {
      const $indicators = $('<span>').addClass('user-name-indicators');
      if (showSsoIndicator) {
        const indicatorTooltip = isAutoProvisioned
          ? '<?= h(__('userList_auto_provisioned_tooltip')) ?>'.replace('%s', identitySource || 'SSO')
          : '<?= h(__('userList_sso_identity_tooltip')) ?>';
        $indicators.append(
          $('<span>')
            .addClass('auto-provisioned-icon')
            .attr('data-bs-toggle', 'tooltip')
            .attr('data-bs-placement', 'top')
            .attr('title', indicatorTooltip)
            .append($('<i>').addClass('ri-user-add-line'))
        );
      }
      if (isProtectedAccount) {
        $indicators.append(
          $('<span>')
            .addClass('protected-account-badge')
            .attr('data-bs-toggle', 'tooltip')
            .attr('data-bs-placement', 'top')
            .attr('title', '<?= h(__('userList_protected_tooltip')) ?>')
            .text('<?= h(__('userList_protected_badge')) ?>')
        );
      }
      $nameShell.append($indicators);
    }
    $tr.append(
      $('<td>').addClass('col-nama').append($nameShell)
    );

    // Column: jabatan
    $tr.append(
      $('<td>').addClass('col-jabatan').append(
        $('<span>')
          .addClass('truncate-1line cell-tooltip-text')
          .attr('data-bs-toggle', 'tooltip')
          .attr('data-bs-placement', 'top')
          .attr('title', jabatan || '')
          .text(jabatan)
      )
    );

    // Column: group badge
    const $groupTd = $('<td>').addClass('col-group');
    const $cellInline = $('<span>').addClass('cell-inline');
    const $badge = $('<span>')
      .addClass('group-chip cell-tooltip-text')
      .attr('data-bs-toggle', 'tooltip')
      .attr('data-bs-placement', 'top')
      .attr('title', gName || '')
      .text(gName);
    const inlineBadgeStyle = explicitRowColor
      ? `background-color:${explicitRowColor};color:${getReadableTextColor(explicitRowColor)};`
      : getBadgeInlineStyle(gId, gKod);
    if (inlineBadgeStyle) {
      $badge.attr('style', inlineBadgeStyle);
    }
    const $info = $('<i>')
      .addClass('ri-information-line text-muted extra-roles-info')
      .attr('data-has-extra', extraRoles.length > 0 ? '1' : '0')
      .attr('data-bs-toggle', 'tooltip')
      .attr('data-bs-placement', 'top');
    renderExtraRolesInfo($info[0], extraRoles);
    $cellInline.append($badge).append($info);
    $groupTd.append($cellInline);
    $tr.append($groupTd);

    // Column: akses badge
    const $aksesTd = $('<td>').addClass('col-akses');
    const $aksesBadge = $('<span>').addClass('access-chip');
    if (parseInt(flag, 10) === 1) {
      $aksesBadge
        .addClass('is-allowed cell-tooltip-text')
        .attr('data-bs-toggle', 'tooltip')
        .attr('data-bs-placement', 'top')
        .attr('title', '<?= h(__('userList_access_granted')) ?>')
        .text('<?= h(__('userList_access_granted')) ?>');
    } else {
      $aksesBadge
        .addClass('is-blocked cell-tooltip-text')
        .attr('data-bs-toggle', 'tooltip')
        .attr('data-bs-placement', 'top')
        .attr('title', '<?= h(__('userList_access_blocked')) ?>')
        .text('<?= h(__('userList_access_blocked')) ?>');
    }
    $aksesTd.append($aksesBadge);
    $tr.append($aksesTd);

    // Column: actions
    const $actionsTd = $('<td>').addClass('col-actions');
    if (canEditGroup) {
      if (canViewAsUser) {
        const $viewAsBtn = $('<button>').attr('type','button').addClass('btn btn-outline-warning btn-sm icon-btn btn-view-as-user')
          .attr('title', '<?= h(__('impersonation_view_as_action')) ?>')
          .attr('data-loginid', loginID)
          .attr('data-nama', nama)
          .attr('data-displayid', visibleIdentifier)
          .html('<i class="ri-eye-line"></i>');
        $actionsTd.append($viewAsBtn);
      }
      const $editBtn = $('<button>').attr('type','button').addClass('btn btn-outline-primary btn-sm icon-btn btn-edit-group')
        .attr('title', '<?= h(__('userList_action_change_group')) ?>')
        .attr('data-user-id', userID)
        .attr('data-nama', nama)
        .attr('data-stafid', stafID)
        .attr('data-loginid', loginID)
        .attr('data-nickname', nickname)
        .attr('data-email', email)
        .attr('data-phone', phone)
        .attr('data-university', university)
        .attr('data-nokp', nokp)
        .attr('data-displayid', visibleIdentifier)
        .attr('data-nopekerja', nopekerja)
        .attr('data-avatar-url', avatarUrl)
        .attr('data-jabatan', jabatan)
        .attr('data-group-id', String(gId || ''))
        .attr('data-group-kod', gKod)
        .attr('data-group-name', gName)
        .attr('data-scope', categoryUser === 'PELAJAR' ? 'student' : (categoryUser === 'UMUM' ? 'public' : 'staff'))
        .attr('data-flag', String(flag))
        .html('<i class="ri-pencil-line"></i>');
      if (canViewAsUser) {
        $editBtn.addClass('ms-1');
      }

      $actionsTd.append($editBtn);
      if (canDeleteUser) {
        const $delBtn = $('<button>').attr('type','button').addClass('btn btn-outline-danger btn-sm icon-btn btn-delete-user ms-1')
          .attr('title', '<?= h(__('userList_action_delete_user')) ?>')
          .attr('data-user-id', userID)
          .attr('data-nama', nama)
          .attr('data-stafid', stafID)
          .attr('data-displayid', visibleIdentifier)
          .html('<i class="ri-delete-bin-line"></i>');
        $actionsTd.append($delBtn);
      }
    }
    $tr.append($actionsTd);

    return $tr;
  }

  // Function untuk reload table via AJAX (tanpa refresh page)
  async function reloadUserTable(highlightUserID = null) {
    // Cancel previous request if exists
    if (currentRequestController) {
      currentRequestController.abort();
    }
    
    currentRequestController = new AbortController();
    
    showLoading('<?= h(__('userList_loading_user_list')) ?>');
    
    try {
      trackEvent('user_list_reload', { highlightUserID });
      
      const r = await fetchWithRetry('<?= base_url('ajax/user-list-rows.php') ?>', {
        headers: { 'Accept': 'application/json' },
        signal: currentRequestController.signal
      });
      
      if (!r.ok) {
        let errorText = '<?= h(__('userList_http_status_prefix')) ?> ' + r.status;
        try {
          const errorData = await r.text();
          try {
            const errorJson = JSON.parse(errorData);
            errorText = errorJson.message || errorText;
          } catch (e) {
            errorText = errorData.substring(0, 200);
          }
        } catch (e) {
          // Ignore
        }
        throw new Error(errorText);
      }
      
      const contentType = r.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        const text = await r.text();
        throw new Error('<?= h(__('userList_err_non_json')) ?>');
      }
      
      const j = await r.json();
      if (j.error) throw new Error(j.message || '<?= h(__('userList_err_load_data')) ?>');
      
      // Jika DataTable sudah wujud, update dengan destroy(false) dan re-init untuk maintain layout
      if ($.fn.DataTable.isDataTable('#userDT')) {
        // Ensure a global safe HTML setter is available
        if (typeof window.setSafeInnerHTML !== 'function') {
          window.setSafeInnerHTML = function(el, html) {
            if (!el) return;
            if (!html) { el.innerHTML = ''; return; }
            if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
              el.innerHTML = DOMPurify.sanitize(html);
              return;
            }
            try {
              var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
              doc.querySelectorAll('script').forEach(function(s){ s.remove(); });
              doc.querySelectorAll('*').forEach(function(n){
                Array.from(n.attributes).forEach(function(a){
                  if (/^on/i.test(a.name)) n.removeAttribute(a.name);
                  if ((a.name === 'src' || a.name === 'href') && /^javascript:/i.test(a.value)) n.removeAttribute(a.name);
                });
              });
              el.innerHTML = doc.body.firstChild ? doc.body.firstChild.innerHTML : '';
            } catch (e) {
              el.innerHTML = html;
            }
          };
        }

        const dt = $('#userDT').DataTable();
        
        // Preserve current state
        const currentPage = dt.page();
        const currentSearch = dt.search();
        const currentOrder = dt.order();
        const currentLength = dt.page.len();
        
        const rowData = Array.isArray(j.rows) ? j.rows : [];
        const $newRows = $(rowData.map(r => buildRowFromData(r).get(0)));
        
        // Clear existing rows (tanpa destroy untuk maintain layout)
        dt.clear();
        
        const expectedColumnCount = $('#userDT thead th').length || 6;

        // Add new rows - pastikan rows match dengan table structure semasa
        if ($newRows.length > 0) {
          const rowsArray = [];
          $newRows.each(function() {
            const $row = $(this);
            // Pastikan row ada semua columns yang diperlukan ikut layout semasa
            const tdCount = $row.find('td').length;
            if (tdCount === expectedColumnCount) {
              rowsArray.push(this);
            }
          });
          
          if (rowsArray.length > 0) {
            try {
              dt.rows.add(rowsArray);
            } catch (e) {
              // Fallback: destroy dan re-init
              dt.destroy();
              const $tbody = $('#userDT tbody');
              const nodes = rowData.map(r => buildRowFromData(r).get(0));
              $tbody.html('');
              $tbody.append($(nodes));
              dtInstance = $('#userDT').DataTable({
                pageLength: currentLength || 10,
                lengthChange: true,
                lengthMenu: [10, 25, 50, 100, 200],
                ordering: true,
                order: currentOrder.length > 0 ? currentOrder : [[1,'asc']],
                autoWidth: false,
                scrollX: false,
                dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
                  't' +
                  '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
                // ✅ Pastikan length selector tidak wrap
                lengthMenu: [10, 25, 50, 100, 200],
                language: {
                  lengthMenu: "<?= h(__('userList_dt_length_menu')) ?>",
                  search: "",
                  info: "<?= h(__('userList_dt_info')) ?>",
                  infoEmpty: "<?= h(__('userList_dt_info_empty')) ?>",
                  paginate: { previous: "<?= h(__('userList_dt_paginate_prev')) ?>", next: "<?= h(__('userList_dt_paginate_next')) ?>"},
                  zeroRecords: "<?= h(__('userList_dt_zero_records')) ?>"
                },
                columnDefs: [
                  { targets: 0, orderable:false, searchable:false, width: 56 },
                  { targets: expectedColumnCount - 1, orderable:false, searchable:false, width: 110 }
                ],
                rowCallback: function(row, data, displayIndex){
                  const api  = this.api();
                  const info = api.page.info();
                  $('td:eq(0)', row).text(info.start + displayIndex + 1);
                  
                  const $row = $(row);
                  applyRowClass($row);
                },
                initComplete: function() {
                  setupTableControls();
                  try {
                    const _lbl = <?= json_encode(h(__('userList_dt_search_label'))) ?>;
                    const _ph = String(_lbl).replace(/[:：\s]+$/, '').trim();
                    $('#userDT_filter input').attr('placeholder', _ph);
                  } catch(e) { /* ignore */ }
                }
              });
              dt = dtInstance;
              if (currentSearch) {
                dt.search(currentSearch);
              }
              if (currentLength) {
                dt.page.len(currentLength);
              }
              const pageInfo = dt.page.info();
              const targetPage = Math.min(currentPage, Math.max(0, pageInfo.pages - 1));
              if (targetPage >= 0 && targetPage < pageInfo.pages) {
                dt.page(targetPage);
              }
              dt.draw();
              return; // Exit early
            }
          }
        }
        
        // Restore state
        dt.order(currentOrder);
        dt.search(currentSearch);
        if (currentLength) {
          dt.page.len(currentLength);
        }
        
        // Restore page position
        const pageInfo = dt.page.info();
        const targetPage = Math.min(currentPage, Math.max(0, pageInfo.pages - 1));
        if (targetPage >= 0 && targetPage < pageInfo.pages) {
          dt.page(targetPage);
        }
        
        // Draw dengan false untuk avoid full redraw dan maintain layout
        dt.draw(false);
        
        // Update row numbers dan highlighting (tanpa trigger layout change)
        // Re-get pageInfo selepas draw untuk accurate row numbers
        const currentPageInfo = dt.page.info();
        dt.rows().every(function() {
          const row = this.node();
          const displayIndex = this.index();
          $('td:eq(0)', row).text(currentPageInfo.start + displayIndex + 1);
          
          const $row = $(row);
          applyRowClass($row);
        });
        
        // Highlight row jika ada userID yang perlu di-highlight
        if (highlightUserID) {
          setTimeout(() => {
            // Cari row di semua halaman (termasuk yang filtered)
            const $targetRow = $(`#userDT tbody tr[data-user-id="${highlightUserID}"]`);
            if ($targetRow.length > 0) {
              // Pastikan row visible (jika filtered, navigate ke page yang betul)
              const rowIndex = dt.rows({ search: 'applied' }).nodes().indexOf($targetRow[0]);
              if (rowIndex >= 0) {
                const pageInfo = dt.page.info();
                const targetPage = Math.floor(rowIndex / pageInfo.length);
                if (targetPage !== pageInfo.page) {
                  dt.page(targetPage).draw(false);
                }
              }
              
              // Add highlight class
              $targetRow.addClass('row-updated-highlight');
              
              // Remove highlight after configured duration
              setTimeout(() => {
                $targetRow.removeClass('row-updated-highlight');
              }, CONFIG.HIGHLIGHT_DURATION);
            }
          }, CONFIG.ANIMATION_DELAY);
        }
        
        // Update dtInstance reference
        dtInstance = dt;
        
        // Re-setup table controls
        setupTableControls();
        initTooltips(document);
        
        hideLoading();
        return;
      }
      
      // Fallback: jika DataTable belum wujud, init seperti biasa
      const $tbody = $('#userDT tbody');
      const nodes = rowData.map(r => buildRowFromData(r).get(0));
      $tbody.html('');
      $tbody.append($(nodes));
      
      dtInstance = $('#userDT').DataTable({
        pageLength: 10,
        lengthChange: true,
        lengthMenu: [10, 25, 50, 100, 200],
        ordering: true,
        order: [[1,'asc']],
        autoWidth: false,
        scrollX: false,
        dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
          't' +
          '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        language: {
          lengthMenu: "<?= h(__('userList_dt_length_menu')) ?>",
          search: "",
          info: "<?= h(__('userList_dt_info')) ?>",
          infoEmpty: "<?= h(__('userList_dt_info_empty')) ?>",
          paginate: { previous: "<?= h(__('userList_dt_paginate_prev')) ?>", next: "<?= h(__('userList_dt_paginate_next')) ?>"},
          zeroRecords: "<?= h(__('userList_dt_zero_records')) ?>"
        },
        columnDefs: [
          { targets: 0, orderable:false, searchable:false, width: 56 },
          { targets: 5, orderable:false, searchable:false, width: 110 }
        ],
        rowCallback: function(row, data, displayIndex){
          const api  = this.api();
          const info = api.page.info();
          $('td:eq(0)', row).text(info.start + displayIndex + 1);
        },
        initComplete: function() {
          setupTableControls();
          initTooltips(document);
          
          // Highlight row jika ada userID yang perlu di-highlight (fallback case)
          if (highlightUserID) {
            setTimeout(() => {
              const $targetRow = $(`#userDT tbody tr[data-user-id="${highlightUserID}"]`);
              if ($targetRow.length > 0) {
                // Scroll to row jika tidak visible
                const rowOffset = $targetRow.offset();
                if (rowOffset) {
                  $('html, body').animate({
                    scrollTop: rowOffset.top - 100
                  }, 500);
                }
                
                // Add highlight class
                $targetRow.addClass('row-updated-highlight');
                
                // Remove highlight after configured duration
                setTimeout(() => {
                  $targetRow.removeClass('row-updated-highlight');
                }, CONFIG.HIGHLIGHT_DURATION);
              }
            }, CONFIG.ANIMATION_DELAY);
          }
        }
      });
      
      hideLoading();
      
    } catch (e) {
      hideLoading();
      
      // Handle abort error gracefully
      if (e.name === 'AbortError') {
        console.log('Request cancelled');
        return;
      }
      
      // Show user-friendly error
      const errorMsg = sanitizeError(e);
      await fireSwal({
        icon: 'error',
        title: '<?= h(__('userList_error_title')) ?>',
        text: errorMsg || '<?= h(__('userList_err_load_data')) ?>',
        confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
        confirmButtonColor: '#dc3545'
      });
      
      trackEvent('user_list_reload_error', { error: errorMsg });
      throw e;
    }
  }

  // Function untuk setup table controls (buttons, filters, etc)
  function setupTableControls() {
    if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
      window.DataTableStandard.decorate('#userDT', {
        searchPlaceholder: <?= json_encode((string)__('userList_search_placeholder')) ?>
      });
    }
    // Styling
    // ✅ Removed form-select-sm untuk besarkan saiz dropdown
    $('#userDT_length select').addClass('form-select w-auto');
    $('#userDT_length label').addClass('mb-0');
    const $topLeft  = $('#userDT_wrapper .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
    const $topRight = $('#userDT_wrapper .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');
    
    // Remove existing buttons jika ada
    $('#btnSyncSybase').remove();
    $('#btnAddUser').remove();
    
    // Button Sync
    if (!document.getElementById('btnSyncSybase')) {
      const $syncBtn = $('<button type="button" id="btnSyncSybase" class="btn btn-primary">' +
          '<i class="ri-refresh-line me-1"></i> <?= h(__('userList_sync_staff_button')) ?>' +
        '</button>');
      
      // Append button ke akhir topRight container (kanan sekali)
      if ($topRight.length) {
        $topRight.append($syncBtn);
      } else {
        // Fallback: append ke filter jika topRight tidak wujud
        const $filter = $('#userDT_filter');
        if ($filter.length) {
          $filter.append($syncBtn);
        }
      }
      
      $syncBtn.on('click', createRateLimitedHandler(async function(e){
        e.preventDefault();
        
          const $btn = $(this);
          const originalHtml = $btn.html();
          const originalDisabled = $btn.prop('disabled');
          
          $btn.prop('disabled', true);
          $btn.html('<i class="ri-loader-4-line ri-spin me-1"></i> <?= h(__('userList_sync_processing')) ?>');
          
          try {
            trackEvent('user_sync_sybase', {});
            
            const r = await fetchWithRetry('<?= base_url('ajax/user-sync-sybase.php') ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'Accept': 'application/json'
              }
            });
            
            if (!r.ok) throw new Error('<?= h(__('userList_http_status_prefix')) ?> ' + r.status);
            const j = await r.json();
            if (j.error) throw new Error(j.message || '<?= h(__('userList_sync_error')) ?>');
            
            trackEvent('user_sync_sybase_success', { updated: j.updated || 0 });
            
            await fireSwal({
              icon: 'success',
              title: '<?= h(__('userList_sync_success_title')) ?>',
              html:
                '<div class="sync-swal-wrap">' +
                  '<div class="sync-swal-banner">' +
                    '<div class="sync-swal-banner-icon"><i class="ri-checkbox-circle-line"></i></div>' +
                    '<div>' +
                      '<div class="sync-swal-banner-title"><?= h(__('userList_sync_success_title')) ?></div>' +
                      '<div class="sync-swal-banner-text">' + (j.message || '<?= h(__('userList_sync_success_message')) ?>') + '</div>' +
                    '</div>' +
                  '</div>' +
                  '<div class="sync-swal-card">' +
                    '<div class="sync-swal-card-title"><i class="ri-bar-chart-box-line"></i><?= h(__('userList_sync_summary_title')) ?></div>' +
                    '<div class="sync-swal-stats">' +
                      '<div class="sync-swal-stat">' +
                        '<div class="sync-swal-stat-label"><?= h(__('userList_sync_updated')) ?></div>' +
                        '<div class="sync-swal-stat-value is-success">' + (j.updated || 0) + '</div>' +
                      '</div>' +
                      '<div class="sync-swal-stat">' +
                        '<div class="sync-swal-stat-label"><?= h(__('userList_sync_skipped')) ?></div>' +
                        '<div class="sync-swal-stat-value is-warning">' + (j.skipped || 0) + '</div>' +
                      '</div>' +
                      '<div class="sync-swal-stat">' +
                        '<div class="sync-swal-stat-label"><?= h(__('userList_sync_errors')) ?></div>' +
                        '<div class="sync-swal-stat-value is-danger">' + (j.errors || 0) + '</div>' +
                      '</div>' +
                      '<div class="sync-swal-stat">' +
                        '<div class="sync-swal-stat-label"><?= h(__('userList_sync_total')) ?></div>' +
                        '<div class="sync-swal-stat-value is-primary">' + (j.total || 0) + '</div>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>',
              confirmButtonText: '<i class="ri-check-line me-1"></i><?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#198754',
              buttonsStyling: true,
              allowOutsideClick: false,
              allowEscapeKey: false,
              showCloseButton: false,
              width: '480px',
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                confirmButton: 'swal2-confirm-custom'
              }
            });
            
            await reloadUserTable();
          } catch (e) {
            const errorMsg = sanitizeError(e);
            trackEvent('user_sync_sybase_error', { error: errorMsg });
            
            await fireSwal({
              icon: 'error',
              title: '<?= h(__('userList_sync_error_title')) ?>',
              text: errorMsg || '<?= h(__('userList_sync_error')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#dc3545'
            });
          } finally {
            $btn.prop('disabled', originalDisabled);
            $btn.html(originalHtml);
          }
      }, 2000));
    }
    
    // Button Tambah Pengguna (Super Admin sahaja)
    if (canAddUsers && !document.getElementById('btnAddUser')) {
      const $addBtn = $('<button type="button" id="btnAddUser" class="btn btn-success" onclick="return window.userListOpenAdd ? window.userListOpenAdd(\'staff\') : false;">' +
          '<i class="ri-user-add-line me-1"></i> <?= h(__('userList_add_button')) ?>' +
        '</button>');
      $addBtn.attr('data-modal-bound', '1');
      
      // Append button ke akhir topRight container (kanan sekali, selepas btnSyncSybase jika ada)
      if ($topRight.length) {
        if (document.getElementById('btnSyncSybase')) {
          $('#btnSyncSybase').after($addBtn);
        } else {
          $topRight.append($addBtn);
        }
      } else {
        // Fallback: append ke filter jika topRight tidak wujud
        const $filter = $('#userDT_filter');
        if ($filter.length) {
          if (document.getElementById('btnSyncSybase')) {
            $('#btnSyncSybase').after($addBtn);
          } else {
            $filter.append($addBtn);
          }
        }
      }
      
      $addBtn.on('click', async function(e){
        e.preventDefault();
        if (window.userListOpenAdd) {
          await window.userListOpenAdd('staff');
        }
      });
    }
    // Ensure search input has placeholder from translation (strip trailing colon)
    try {
      const _lbl = <?= json_encode(h(__('userList_dt_search_label'))) ?>;
      const _ph = String(_lbl).replace(/[:：\s]+$/, '').trim();
      const $inp = $('#userDT_filter input');
      if ($inp.length) $inp.attr('placeholder', _ph);
    } catch(e) { /* ignore */ }
  }

  // Helper: auto-size select ikut teks option yang terpilih
  function fitSelectWidth(sel){
    if (!sel) return;
    if (sel.classList && sel.classList.contains('dt-group-filter')) {
      sel.style.width = '210px';
      sel.style.minWidth = '210px';
      sel.style.maxWidth = '210px';
      return;
    }
    sel.style.width = 'auto';
    const span = document.createElement('span');
    span.style.visibility = 'hidden';
    span.style.position   = 'fixed';
    span.style.whiteSpace = 'pre';
    const cs = window.getComputedStyle(sel);
    span.style.font = cs.font || `${cs.fontSize} ${cs.fontFamily}`;
    span.style.fontSize   = cs.fontSize;
    span.style.fontFamily = cs.fontFamily;
    span.textContent = sel.options[sel.selectedIndex]?.text || sel.value || '';
    document.body.appendChild(span);
    const padX = 28;
    const w = Math.ceil(span.getBoundingClientRect().width) + padX;
    document.body.removeChild(span);
    sel.style.width = w + 'px';
  }

  function normalizeScope(scope) {
    const safeScope = String(scope || 'staff').trim().toLowerCase();
    if (safeScope === 'pelajar') return 'student';
    if (safeScope === 'umum') return 'public';
    if (safeScope === 'student' || safeScope === 'public') return safeScope;
    return 'staff';
  }

  function normalizeGroupOption(group) {
    const id = group && (group.id || group.f_groupID || '');
    const kod = group && (group.kod || group.f_groupKod || '');
    const name = group && (group.nama || group.f_groupName || kod || '');
    return { id, kod, name };
  }

  function getEmbeddedGroupsForScope(scope) {
    const safeScope = normalizeScope(scope);
    const groupsByScope = CONFIG.GROUPS_BY_SCOPE || {};
    return Array.isArray(groupsByScope[safeScope]) ? groupsByScope[safeScope] : [];
  }

  async function fetchGroupsForScope(scope) {
    const safeScope = normalizeScope(scope);
    try {
      const res = await fetch(`<?= base_url('ajax/group-list.php') ?>?scope=${encodeURIComponent(safeScope)}`, { headers: { 'Accept':'application/json' } });
      if (res.ok) {
        const j = await res.json();
        if (j && Array.isArray(j.groups)) {
          return j.groups;
        }
      }
    } catch (e) { /* use embedded fallback */ }
    return getEmbeddedGroupsForScope(safeScope);
  }

  function getScopeMeta(scope) {
    const normalized = String(scope || 'staff').trim().toLowerCase();
    if (normalized === 'student' || normalized === 'pelajar') {
      if (!CONFIG.STUDENT_MODE_ENABLED) {
        return {
          scope: 'staff',
          tableId: 'userDT',
          filterId: 'dtGroupFilter',
          syncButtonId: 'btnSyncSybase',
          addButtonId: 'btnAddUser',
          addLabel: '<?= h(__('userList_add_button')) ?>'
        };
      }
      return {
        scope: 'student',
        tableId: 'userDTStudent',
        filterId: 'dtGroupFilterStudent',
        syncButtonId: 'btnSyncStudent',
        addButtonId: 'btnAddUserStudent',
        addLabel: '<?= h(__('userList_add_student_button')) ?>'
      };
    }
    if (normalized === 'public' || normalized === 'umum') {
      return {
        scope: 'public',
        tableId: 'userDTPublic',
        filterId: 'dtGroupFilterPublic',
        syncButtonId: 'btnSyncPublic',
        addButtonId: 'btnAddUserPublic',
        addLabel: '<?= h(__('userList_add_public_button')) ?>'
      };
    }
    return {
      scope: 'staff',
      tableId: 'userDT',
      filterId: 'dtGroupFilter',
      syncButtonId: 'btnSyncSybase',
      addButtonId: 'btnAddUser',
      addLabel: '<?= h(__('userList_add_button')) ?>'
    };
  }

  function drawTableRowNumbers(dt) {
    if (!dt || typeof dt.rows !== 'function') return;
    const pageInfo = dt.page.info();
    dt.rows({ page: 'current' }).every(function(displayIndex) {
      $('td:eq(0)', this.node()).text(pageInfo.start + displayIndex + 1);
      applyRowClass($(this.node()));
    });
  }

  async function populateSelectGroupsForScope(selectEl, scope, selectedId = '') {
    if (!selectEl) return;
    try {
      const safeScope = normalizeScope(scope);
      const groups = await fetchGroupsForScope(safeScope);
      selectEl.innerHTML = '';
      let optionCount = 0;
      groups.forEach(g => {
        const { id, name } = normalizeGroupOption(g);
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        if (selectedId && String(selectedId) === String(id)) opt.selected = true;
        selectEl.appendChild(opt);
        if (id) optionCount++;
      });
      if (!selectedId && optionCount === 1 && safeScope !== 'staff' && selectEl.options[0]) {
        selectEl.value = selectEl.options[0].value;
      }
    } catch (e) { /* ignore */ }
  }

  function removeUserRowFromTable(tableId, userID) {
    const selector = `#${tableId}`;
    const $row = $(`${selector} tbody tr[data-user-id="${userID}"]`);
    if (!$row.length) return;
    if ($.fn.DataTable.isDataTable(selector)) {
      const dt = $(selector).DataTable();
      dt.row($row).remove().draw(false);
      drawTableRowNumbers(dt);
      return;
    }
    $row.remove();
  }

  function appendUserRowToTable(tableId, rowData) {
    if (!rowData || typeof rowData !== 'object') return null;
    const selector = `#${tableId}`;
    const tableEl = document.querySelector(selector);
    if (!tableEl) return null;

    const rowNode = buildRowFromData(rowData).get(0);
    if (!rowNode) return null;

    if ($.fn.DataTable.isDataTable(selector)) {
      const dt = $(selector).DataTable();
      const expectedColumnCount = $(`${selector} thead th`).length || 6;
      const tdCount = rowNode.querySelectorAll('td').length;
      if (tdCount !== expectedColumnCount) {
        return null;
      }
      const inserted = dt.row.add(rowNode).draw(false).node();
      drawTableRowNumbers(dt);
      if (inserted) {
        initTooltips(inserted);
      }
      return inserted || rowNode;
    }

    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return null;
    tbody.appendChild(rowNode);
    initTooltips(rowNode);
    return rowNode;
  }

  function setupScopedTableControls(tableId, scope, options = {}) {
    const selector = `#${tableId}`;
    const wrapperSelector = `${selector}_wrapper`;
    const meta = getScopeMeta(scope);
    const addLabel = options.addLabel || meta.addLabel;

    if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
      window.DataTableStandard.decorate(selector, {
        searchPlaceholder: <?= json_encode((string)__('userList_search_placeholder')) ?>
      });
    }

    $(`${wrapperSelector} .dataTables_length select`).addClass('form-select w-auto');
    $(`${wrapperSelector} .dataTables_length label`).addClass('mb-0');
    const $topLeft = $(`${wrapperSelector} .dt-top-left`).addClass('d-flex align-items-center gap-2 flex-nowrap');
    const $topRight = $(`${wrapperSelector} .dt-top-right`).addClass('align-items-center gap-2 flex-nowrap');

    $(`#${meta.filterId}`).remove();
    $(`#${meta.addButtonId}`).remove();

    const $grp = $(`<select id="${meta.filterId}" class="form-select dt-group-filter"><option value=""><?= h(__('userList_group_filter_placeholder')) ?></option></select>`);
    const $filter = $(`${selector}_filter`);
    if ($filter.length) {
      $filter.after($grp);
    } else {
      $topRight.append($grp);
    }

    (async () => {
      try {
        const groups = await fetchGroupsForScope(meta.scope);
        let optionCount = 0;
        groups.forEach(g => {
          const { id, name } = normalizeGroupOption(g);
          if (!id || !name) return;
          $grp.append(new Option(name, String(id)));
          optionCount++;
        });
        if (meta.scope !== 'staff' && optionCount === 1) {
          $grp.val($grp.find('option:last').val());
          $grp.trigger('change');
        }
      } catch (e) { /* ignore */ }
      fitSelectWidth($grp[0]);
    })();

    const dt = $.fn.DataTable.isDataTable(selector) ? $(selector).DataTable() : null;
    let groupFilterId = '';
    $grp.off('change').on('change', function() {
      groupFilterId = this.value || '';
      if (dt) {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
          if (!settings || settings.nTable?.id !== tableId) return true;
          if (!groupFilterId) return true;
          const rowNode = dt.row(dataIndex).node();
          const gid = rowNode ? (rowNode.getAttribute('data-group-id') || '') : '';
          return String(gid) === String(groupFilterId);
        });
        dt.draw();
      }
      fitSelectWidth(this);
    });

    $(`#${meta.syncButtonId}`).remove();
    if (meta.scope === 'student' && !document.getElementById(meta.syncButtonId)) {
      const syncLabel = meta.scope === 'student'
        ? '<?= h(__('userList_sync_student_button')) ?>'
        : '<?= h(__('userList_sync_button')) ?>';
      const $syncBtn = $(`<button type="button" id="${meta.syncButtonId}" class="btn btn-primary"><i class="ri-refresh-line me-1"></i> ${syncLabel}</button>`);
      $topRight.append($syncBtn);
      $syncBtn.on('click', async function(e) {
        e.preventDefault();
        if (window.Swal) {
          await fireSwal({
            icon: 'info',
            title: '<?= h(__('userList_sync_student_pending_title')) ?>',
            text: '<?= h(__('userList_sync_student_pending_text')) ?>',
            confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
            confirmButtonColor: '#0d6efd'
          });
        }
      });
    }

    if (canAddUsers && !document.getElementById(meta.addButtonId)) {
      const $addBtn = $(`<button type="button" id="${meta.addButtonId}" class="btn btn-success" onclick="return window.userListOpenAdd ? window.userListOpenAdd('${meta.scope}') : false;"><i class="ri-user-add-line me-1"></i> ${addLabel}</button>`);
      $addBtn.attr('data-modal-bound', '1');
      $topRight.append($addBtn);
      $addBtn.on('click', async function(e) {
        e.preventDefault();
        if (window.userListOpenAdd) {
          await window.userListOpenAdd(meta.scope);
        }
      });
    }

    try {
      const _lbl = <?= json_encode(h(__('userList_dt_search_label'))) ?>;
      const _ph = String(_lbl).replace(/[:：\s]+$/, '').trim();
      const $inp = $(`${selector}_filter input`);
      if ($inp.length) $inp.attr('placeholder', _ph);
    } catch(e) { /* ignore */ }
  }

  function initScopedDataTable(tableId, scope) {
    const tableSelector = `#${tableId}`;
    if (!document.querySelector(tableSelector)) return null;
    const expectedColumnCount = $(`${tableSelector} thead th`).length || 6;
    const lastColumnIndex = Math.max(0, expectedColumnCount - 1);
    if ($.fn.DataTable.isDataTable(tableSelector)) {
      const existing = $(tableSelector).DataTable();
      const addLabel = scope === 'student'
        ? '<?= h(__('userList_add_student_button')) ?>'
        : '<?= h(__('userList_add_public_button')) ?>';
      setupScopedTableControls(tableId, scope, { addLabel });
      existing.columns.adjust().draw(false);
      return existing;
    }

    const dt = $(tableSelector).DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [10, 25, 50, 100, 200],
      ordering: true,
      order: [[1,'asc']],
      autoWidth: false,
      scrollX: false,
      dom:
        '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
        't' +
        '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
      language: {
        lengthMenu: "<?= h(__('userList_dt_length_menu')) ?>",
        search: "",
        info: "<?= h(__('userList_dt_info')) ?>",
        infoEmpty: "<?= h(__('userList_dt_info_empty')) ?>",
        emptyTable: "<?= h(__('userList_no_records')) ?>",
        paginate: { previous: "<?= h(__('userList_dt_paginate_prev')) ?>", next: "<?= h(__('userList_dt_paginate_next')) ?>"},
        zeroRecords: "<?= h(__('userList_dt_zero_records')) ?>"
      },
      columnDefs: [
        { targets: 0, orderable:false, searchable:false, width: 56 },
        { targets: lastColumnIndex, orderable:false, searchable:false, width: 110 }
      ],
      rowCallback: function(row, data, displayIndex){
        const api = this.api();
        const info = api.page.info();
        $('td:eq(0)', row).text(info.start + displayIndex + 1);
        applyRowClass($(row));
      },
      initComplete: function() {
        const addLabel = scope === 'student'
          ? '<?= h(__('userList_add_student_button')) ?>'
          : '<?= h(__('userList_add_public_button')) ?>';
        setupScopedTableControls(tableId, scope, { addLabel });
        initTooltips(document.querySelector(tableSelector) || document);
      },
      drawCallback: function() {
        drawTableRowNumbers(this.api());
        initTooltips(document.querySelector(tableSelector) || document);
      }
    });
    return dt;
  }

  document.addEventListener('DOMContentLoaded', function(){
    if (!hasDT()) { return; }

    // Re-init guard
    if ($.fn.DataTable.isDataTable('#userDT')) {
      $('#userDT').DataTable().destroy();
    }

    const dt = $('#userDT').DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [10, 25, 50, 100, 200],
      ordering: true,
      order: [[1,'asc']],                 // ikut kolum Nama (StafID)
      autoWidth: false,
      scrollX: false,
      dom:
        '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
        't' +
        '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
      language: {
        lengthMenu: "<?= h(__('userList_dt_length_menu')) ?>",
        search: "",
        info: "<?= h(__('userList_dt_info')) ?>",
        infoEmpty: "<?= h(__('userList_dt_info_empty')) ?>",
        emptyTable: "<?= h(__('userList_no_records')) ?>",
        paginate: { previous: "<?= h(__('userList_dt_paginate_prev')) ?>", next: "<?= h(__('userList_dt_paginate_next')) ?>"},
        zeroRecords: "<?= h(__('userList_dt_zero_records')) ?>"
      },
      columnDefs: [
        { targets: 0, orderable:false, searchable:false, width: 56 },  // Bil
        { targets: Math.max(0, ($('#userDT thead th').length || 6) - 1), orderable:false, searchable:false, width: 110 }  // Tindakan (ikon)
      ],
        initComplete: function() {
          try {
            const _lbl = <?= json_encode(h(__('userList_dt_search_label'))) ?>;
            const _ph = String(_lbl).replace(/[:：\s]+$/, '').trim();
            $('#userDT_filter input').attr('placeholder', _ph);
          } catch(e) { /* ignore */ }
        },
      rowCallback: function(row, data, displayIndex){
        const api  = this.api();
        const info = api.page.info();
        $('td:eq(0)', row).text(info.start + displayIndex + 1);
        
        // Apply row highlighting based on group (if not already applied from server-side)
        const $row = $(row);
        applyRowClass($row);
      },
      initComplete: function() {
        setupTableControls();
        initTooltips(document.querySelector('#userDT') || document);
      },
      drawCallback: function() {
        drawTableRowNumbers(this.api());
        initTooltips(document.querySelector('#userDT') || document);
      }
    });
    
    // Set dtInstance untuk digunakan dalam functions lain
    dtInstance = dt;

    // === Styling & susun kiri/kanan (sebaris, tak berbalut) ===
    // ✅ Removed form-select-sm untuk besarkan saiz dropdown
    $('#userDT_length select')
      .addClass('form-select w-auto');

    $('#userDT_length label').addClass('mb-0');
    const $topLeft  = $('#userDT_wrapper .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
    const $topRight = $('#userDT_wrapper .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');

    // === Dropdown Filter Kumpulan (auto width) — duduk di sebelah carian, sebelum button ===
    const $grp = $(`
      <select id="dtGroupFilter" class="form-select">
        <option value=""><?= h(__('userList_group_filter_placeholder')) ?></option>
      </select>
    `);
    // Append ke topRight selepas search box tapi sebelum button
    // Search box adalah #userDT_filter, jadi kita append selepas filter
    const $filter = $('#userDT_filter');
    if ($filter.length) {
      $filter.after($grp);
    } else {
      // Fallback: append ke topRight (akan duduk sebelum button kerana button di-append selepas)
      $topRight.append($grp);
    }

    // Ambil senarai kumpulan & populate option (guna ID untuk penapisan tepat)
    (async () => {
      try {
        const groups = await fetchGroupsForScope('staff');
        groups.forEach(g => {
          const { id, name } = normalizeGroupOption(g);
          if (!id || !name) return;
          $grp.append(new Option(name, String(id)));
        });
      } catch (e) { }
      fitSelectWidth($grp[0]);
    })();

    // Helper: escape regex
    function escRx(s){ return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    // Tapis ikut kumpulan berdasarkan data-group-id + auto-size bila berubah
    let groupFilterId = '';
    if (!window.__userDTGroupFilterAdded) {
      $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (!settings || settings.nTable?.id !== 'userDT') return true;
        if (!groupFilterId) return true;
        const rowNode = dt.row(dataIndex).node();
        const gid = rowNode ? (rowNode.getAttribute('data-group-id') || '') : '';
        return String(gid) === String(groupFilterId);
      });
      window.__userDTGroupFilterAdded = true;
    }
    $('#dtGroupFilter').on('change', function(){
      groupFilterId = this.value || '';
      dt.draw();
      fitSelectWidth(this);
    });

    // Resize semula bila window berubah (optional)
    window.addEventListener('resize', () => fitSelectWidth(document.getElementById('dtGroupFilter')));

    // Setup table controls (buttons, filters) - ini akan handle semua termasuk dropdown filter
    setupTableControls();

    const userAccessTabs = document.getElementById('userAccessTabs');
    if (userAccessTabs) {
      userAccessTabs.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(tabBtn) {
        tabBtn.addEventListener('click', function(e) {
          const targetSelector = e.currentTarget?.getAttribute('data-bs-target') || '';
          window.setTimeout(function() {
            if (targetSelector === '#tab-student-access') {
              if (!CONFIG.STUDENT_MODE_ENABLED) return;
              initScopedDataTable('userDTStudent', 'student');
            } else if (targetSelector === '#tab-public-access') {
              initScopedDataTable('userDTPublic', 'public');
            }
          }, 0);
        });
        tabBtn.addEventListener('shown.bs.tab', function(e) {
          const targetSelector = e.target?.getAttribute('data-bs-target') || '';
          try {
            if (targetSelector) {
              if (!CONFIG.STUDENT_MODE_ENABLED && targetSelector === '#tab-student-access') {
                sessionStorage.setItem('userListActiveTab', '#tab-staff-access');
              } else {
              sessionStorage.setItem('userListActiveTab', targetSelector);
              }
            }
          } catch (err) { /* ignore */ }
          if (targetSelector === '#tab-student-access') {
            if (!CONFIG.STUDENT_MODE_ENABLED) return;
            initScopedDataTable('userDTStudent', 'student');
          } else if (targetSelector === '#tab-public-access') {
            initScopedDataTable('userDTPublic', 'public');
          } else if (targetSelector === '#tab-staff-access' && $.fn.DataTable.isDataTable('#userDT')) {
            $('#userDT').DataTable().columns.adjust().draw(false);
          }
        });
      });
    }

    try {
      const savedActiveTab = sessionStorage.getItem('userListActiveTab') || '';
      if (!CONFIG.STUDENT_MODE_ENABLED && savedActiveTab === '#tab-student-access') {
        sessionStorage.setItem('userListActiveTab', '#tab-staff-access');
      }
      const savedTabBtn = savedActiveTab
        ? userAccessTabs?.querySelector(`[data-bs-target="${savedActiveTab}"]`)
        : null;
      if (savedTabBtn && window.bootstrap && bootstrap.Tab) {
        bootstrap.Tab.getOrCreateInstance(savedTabBtn).show();
      }
    } catch (e) { /* ignore */ }

    window.setTimeout(function() {
      if (CONFIG.STUDENT_MODE_ENABLED) {
        initScopedDataTable('userDTStudent', 'student');
      }
      initScopedDataTable('userDTPublic', 'public');
    }, 120);

    // ===== Modal Tukar Kumpulan =====
    const modalEl = document.getElementById('userGroupModal');
    const modal   = modalEl ? new bootstrap.Modal(modalEl) : null;
    const errEl   = document.getElementById('ug_error');
    const roleModalEl = document.getElementById('roleExtraModal');
    let roleModal = roleModalEl ? new bootstrap.Modal(roleModalEl) : null;
    let restoreParentUserGroupModal = false;
    let restoreParentAfterRoleAlert = false;
    let currentPrimaryRoleName = '';
    let currentUserScope = 'staff';
    let currentAddScope = 'staff';
    const roleListEl = document.getElementById('roleExtraList');
    const roleErrEl = document.getElementById('roleExtraError');

    function showRoleErr(msg){ if(!roleErrEl) return; roleErrEl.textContent = msg || '<?= h(__('userList_err_unknown')) ?>'; roleErrEl.classList.remove('d-none'); }
    function hideRoleErr(){ if(!roleErrEl) return; roleErrEl.classList.add('d-none'); }
    function showErr(msg){ if(!errEl) return; errEl.textContent = msg || '<?= h(__('userList_err_unknown')) ?>'; errEl.classList.remove('d-none'); }
    function hideErr(){ if(!errEl) return; errEl.classList.add('d-none'); }
    function resetPublicEditFields() {
      ['ug_publicName','ug_publicNickname','ug_publicEmail','ug_publicPhone','ug_publicUniversity','ug_publicNoKp','ug_publicPassword','ug_publicPasswordConfirm']
        .forEach(function(id) {
          const el = document.getElementById(id);
          if (el) {
            el.value = '';
            el.classList.remove('field-invalid');
          }
        });
    }
    function resetEditPasswordFields() {
      ['ug_resetPassword', 'ug_resetPasswordConfirm'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
          el.value = '';
          el.classList.remove('field-invalid');
        }
      });
    }
    function configureEditModalForScope(scope) {
      const normalized = String(scope || 'staff').trim().toLowerCase() || 'staff';
      const publicSection = document.getElementById('ug_publicSection');
      const publicTabWrap = document.getElementById('ug-tab-public-wrap');
      const publicTabBtn = document.getElementById('ug-tab-public');
      const passwordSection = document.getElementById('ug_passwordSection');
      const userInfoTabBtn = document.getElementById('ug-tab-userinfo');
      const addRoleBtn = document.getElementById('ug_addRoleBtn');
      const modalTitle = document.getElementById('userGroupTitle');
      const jabatanLabel = document.querySelector('#userGroupModal .info-item:nth-child(2) .info-label');
      const saveBtn = document.getElementById('ug_saveBtn');
      document.getElementById('ug_scope').value = normalized;
      resetPublicEditFields();
      resetEditPasswordFields();
      if (publicSection) publicSection.classList.toggle('d-none', normalized !== 'public');
      if (publicTabWrap) publicTabWrap.classList.toggle('d-none', normalized !== 'public');
      if (passwordSection) passwordSection.classList.toggle('d-none', normalized === 'public');
      if (addRoleBtn) addRoleBtn.classList.toggle('d-none', normalized === 'public');
      if (modalTitle) {
        modalTitle.innerHTML = normalized === 'public'
          ? '<i class="ri-user-settings-line me-2"></i> <?= h(__('userList_modal_title_public')) ?>'
          : '<i class="ri-user-settings-line me-2"></i> <?= h(__('userList_modal_title')) ?>';
      }
      if (jabatanLabel) {
        jabatanLabel.textContent = normalized === 'public'
          ? '<?= h(__('userList_modal_label_public_email')) ?>'
          : '<?= h(__('userList_modal_label_department')) ?>';
      }
      if (saveBtn) {
        saveBtn.innerHTML = normalized === 'public'
          ? '<i class="ri-save-3-line me-1"></i> <?= h(__('userList_modal_btn_save')) ?>'
          : '<i class="ri-save-3-line me-1"></i> <?= h(__('userList_modal_btn_save')) ?>';
      }
      if (window.bootstrap && bootstrap.Tab && userInfoTabBtn) {
        bootstrap.Tab.getOrCreateInstance(userInfoTabBtn).show();
      } else if (publicTabBtn) {
        publicTabBtn.classList.remove('active');
      }
      hideErr();
    }

    function setRoleButton(count, list) {
      const btn = document.getElementById('ug_addRoleBtn');
      if (!btn) return;
      const label = '<?= h(__('userList_modal_add_role')) ?>';
      const cleanLabel = String(label).replace(/^\+\s*/, '').trim();
      const c = (typeof count === 'number') ? count : 0;
      btn.setAttribute('type', 'button');
      btn.innerHTML = `<i class="ri-add-line me-1"></i> ${cleanLabel} (${c})`;
      const title = Array.isArray(list) && list.length ? list.join(', ') : '<?= h(__('userList_role_none')) ?>';
      btn.setAttribute('data-bs-toggle', 'tooltip');
      btn.setAttribute('data-bs-placement', 'top');
      btn.setAttribute('title', title);
      initTooltips(btn);
    }

    async function loadExtraRoles(userID){
      if (!roleListEl) return;
      roleListEl.innerHTML = '';
      try {
        const r = await fetch('<?= base_url('ajax/user-extra-roles.php') ?>', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF, 'Accept':'application/json'},
          body: JSON.stringify({ action: 'get', userID, scope: currentUserScope })
        });
        const j = await r.json();
        if (!r.ok || !j || j.error) throw new Error((j && j.message) || '<?= h(__('userList_err_load_data')) ?>');
        const roles = j.roles || [];
        if (!roles.length) {
          roleListEl.innerHTML = '<div class="text-muted"><?= h(__('userList_role_none')) ?></div>';
          setRoleButton(0, []);
          return;
        }
        const checkedNames = [];
        roles.forEach(role => {
          const rid = role.id || role.f_groupID;
          const rname = role.name || role.f_groupName || '';
          const checked = role.checked ? 'checked' : '';
          if (role.checked) checkedNames.push(rname);
          const item = document.createElement('label');
          item.className = 'role-item';
          item.innerHTML = `
            <input type="checkbox" value="${rid}" ${checked}>
            <span class="role-label">${rname}</span>
          `;
          roleListEl.appendChild(item);
        });
        setRoleButton(checkedNames.length, checkedNames);
      } catch (e) {
        showRoleErr(e.message || '<?= h(__('userList_err_load_data')) ?>');
      }
    }

    function getPrimaryRoleNameFromSelect() {
      const sel = document.getElementById('ug_groupKod');
      if (!sel) return '';
      const opt = sel.selectedOptions && sel.selectedOptions[0] ? sel.selectedOptions[0] : null;
      if (!opt) return '';
      return (opt.textContent || '').trim();
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    async function populateGroups(selectedId, scope = 'staff'){
      try{
        const groups = await fetchGroupsForScope(scope);
        const sel = document.getElementById('ug_groupKod'); if (!sel) return;
        sel.innerHTML = '';
        groups.forEach(g=>{
          const { id, name } = normalizeGroupOption(g);
          const opt = document.createElement('option');
          opt.value = id; opt.textContent = name;
          if (selectedId && String(selectedId) === String(id)) opt.selected = true;
          sel.appendChild(opt);
        });
      }catch(e){ }
    }

    if (table){
      table.addEventListener('click', async function(e){
        const viewAsBtn = e.target.closest('.btn-view-as-user');
        if (viewAsBtn) {
          e.preventDefault();
          if (!canDeleteUsers) {
            await fireSwal({
              icon: 'info',
              title: '<?= h(__('userList_error_title')) ?>',
              text: '<?= h(__('userList_err_no_permission')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#6c757d'
            });
            return;
          }

          const targetLoginId = viewAsBtn.getAttribute('data-loginid') || '';
          const targetName = viewAsBtn.getAttribute('data-nama') || targetLoginId;
          const targetDisplayId = viewAsBtn.getAttribute('data-displayid') || targetLoginId;
          let reason = '';
          let impersonationMode = 'view_only';

          if (window.Swal) {
            const result = await Swal.fire({
              icon: 'warning',
              title: '<?= h(__('impersonation_start_title')) ?>',
              width: 680,
              html: `<div class="text-start small">
                       <div class="mb-2"><?= h(__('impersonation_start_text')) ?></div>
                       <div><strong>${escapeHtml(targetName)}</strong> <span class="text-muted">(${escapeHtml(targetDisplayId)})</span></div>
                       <div class="mt-3">
                         <label class="form-label fw-semibold mb-1"><?= h(__('impersonation_mode_label')) ?></label>
                         <select class="form-select" id="impersonationModeSelect">
                           <option value="view_only" selected><?= h(__('impersonation_mode_view_only')) ?></option>
                           <option value="support_action"><?= h(__('impersonation_mode_support_action')) ?></option>
                         </select>
                         <div class="form-text"><?= h(__('impersonation_mode_help')) ?></div>
                       </div>
                     </div>`,
              input: 'textarea',
              inputLabel: '<?= h(__('impersonation_reason_label')) ?>',
              inputPlaceholder: '<?= h(__('impersonation_reason_placeholder')) ?>',
              inputAttributes: { maxlength: 500 },
              showCancelButton: true,
              confirmButtonText: '<?= h(__('impersonation_start_button')) ?>',
              cancelButtonText: '<?= h(__('userList_modal_btn_cancel')) ?>',
              confirmButtonColor: '#f59e0b',
              preConfirm: (value) => {
                const clean = String(value || '').trim();
                if (!clean) {
                  Swal.showValidationMessage('<?= h(__('impersonation_reason_required')) ?>');
                  return false;
                }
                return clean;
              }
            });
            if (!result.isConfirmed) return;
            reason = String(result.value || '').trim();
            impersonationMode = String(document.getElementById('impersonationModeSelect')?.value || 'view_only');
          } else {
            reason = String(prompt('<?= h(__('impersonation_reason_label')) ?>') || '').trim();
            if (!reason) return;
          }

          const originalHtml = viewAsBtn.innerHTML;
          viewAsBtn.disabled = true;
          viewAsBtn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
          showImpersonationBoxLoader('<?= h(__('impersonation_loading_start') ?: 'Preparing View As...') ?>');
          try {
            const form = new FormData();
            form.set('csrf_token', CSRF);
            form.set('target_login_id', targetLoginId);
            form.set('reason', reason);
            form.set('mode', impersonationMode);
            const response = await fetch('<?= base_url('ajax/impersonation-start.php') ?>', {
              method: 'POST',
              body: form,
              credentials: 'same-origin',
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success !== true) {
              throw new Error(data.message || '<?= h(__('impersonation_start_failed')) ?>');
            }
            window.location.href = data.redirect || '<?= base_url('pages/dashboard.php') ?>';
          } catch (error) {
            hideImpersonationBoxLoader();
            viewAsBtn.disabled = false;
            viewAsBtn.innerHTML = originalHtml;
            await fireSwal({
              icon: 'error',
              title: '<?= h(__('userList_error_title')) ?>',
              text: error.message || '<?= h(__('impersonation_start_failed')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>'
            });
          }
          return;
        }

        // Handle delete button click
        const deleteBtn = e.target.closest('.btn-delete-user');
        if (deleteBtn) {
          e.preventDefault();
          if (!canDeleteUsers) {
            await fireSwal({
              icon: 'info',
              title: '<?= h(__('userList_error_title')) ?>',
              text: '<?= h(__('userList_err_no_permission')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#6c757d'
            });
            return;
          }
          
          // Rate limiting check
          if (!checkRateLimit('user_delete', 2000)) {
            await fireSwal({
              icon: 'warning',
              title: '<?= h(__('userList_rate_limit_title')) ?>',
              text: '<?= h(__('userList_rate_limit_text')) ?>',
              timer: 2000,
              timerProgressBar: true,
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>'
            });
            return;
          }
          
          const userID = deleteBtn.getAttribute('data-user-id');
          const nama = deleteBtn.getAttribute('data-nama') || '<?= h(__('userList_user_default')) ?>';
          const stafID = deleteBtn.getAttribute('data-stafid') || '';
          const displayId = deleteBtn.getAttribute('data-displayid') || stafID;
          const sourceTableId = deleteBtn.closest('table')?.id || 'userDT';
          if (isProtectedStaffAccountClient(stafID)) {
            await fireSwal({
              icon: 'info',
              title: '<?= h(__('userList_error_title')) ?>',
              text: '<?= h(__('userList_protected_delete_denied')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#0d6efd'
            });
            return;
          }
          if (isCurrentLoggedInUserTarget(userID, stafID)) {
            await fireSwal({
              icon: 'info',
              title: '<?= h(__('userList_error_title')) ?>',
              text: '<?= h(__('userList_ajax_delete_self_denied')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#0d6efd'
            });
            return;
          }
          
          // Confirmation dialog
          const result = await fireSwal({
            icon: 'warning',
            title: '<?= h(__('userList_delete_confirm_title')) ?>',
            html: `<p><?= h(__('userList_delete_confirm_message')) ?></p>
                   <p><strong>${nama}</strong> (${displayId})</p>
                   <p class="text-danger"><small><?= h(__('userList_delete_confirm_warning')) ?></small></p>`,
            showCancelButton: true,
            confirmButtonText: '<?= h(__('userList_delete_confirm_yes')) ?>',
            cancelButtonText: '<?= h(__('userList_modal_btn_cancel')) ?>',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
          });
          
          if (!result.isConfirmed) return;
          
          trackEvent('user_delete', { userID, nama, stafID: displayId });
          
          // Disable button during request
          deleteBtn.disabled = true;
          const originalHTML = deleteBtn.innerHTML;
          deleteBtn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
          
          try {
            const r = await fetchWithRetry('<?= base_url('ajax/user-delete.php') ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              body: JSON.stringify({
                userID: userID,
                csrf_token: CSRF
              })
            });
            
            // Read response once
            let responseText = '';
            let j = null;
            
            try {
              responseText = await r.text();
              j = JSON.parse(responseText);
            } catch (e) {
              throw new Error(`<?= h(__('userList_err_invalid_response')) ?> (${r.status}).`);
            }
            
            if (!r.ok) {
              let errorMsg = '<?= h(__('userList_err_delete_failed')) ?>';
              if (j && j.message) {
                errorMsg = j.message;
              } else {
            errorMsg = `<?= h(__('userList_http_status_prefix')) ?> ${r.status}: ${r.statusText || '<?= h(__('userList_err_server')) ?>'}`;
              }
              throw new Error(errorMsg);
            }
            
            if (!j || j.error) {
              throw new Error((j && j.message) || '<?= h(__('userList_err_delete_failed')) ?>');
            }
            
            trackEvent('user_delete_success', { userID });
            
            if (sourceTableId === 'userDT') {
              await reloadUserTable();
              setupTableControls();
              await refreshStafDropdown();
            } else {
              removeUserRowFromTable(sourceTableId, userID);
              const sourceScope = sourceTableId === 'userDTStudent' ? 'student' : 'public';
              setupScopedTableControls(
                sourceTableId,
                sourceScope,
                { addLabel: sourceScope === 'student' ? '<?= h(__('userList_add_student_button')) ?>' : '<?= h(__('userList_add_public_button')) ?>' }
              );
            }
            
            // Show success message
            await fireSwal({
              icon: 'success',
              title: '<?= h(__('userList_success_title')) ?>',
              text: (j.message || '<?= h(__('userList_success_delete')) ?>'),
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#28a745',
              timer: 2000,
              timerProgressBar: true
            });
          } catch (e) {
            const errorMsg = sanitizeError(e);
            trackEvent('user_delete_error', { userID, error: errorMsg });
            
            await fireSwal({
              icon: 'error',
              title: '<?= h(__('userList_error_title')) ?>',
              text: errorMsg || '<?= h(__('userList_err_delete_failed')) ?>',
              confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
              confirmButtonColor: '#dc3545'
            });
          } finally {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHTML;
          }
          
          return;
        }
        
        // Handle edit button click
        const btn = e.target.closest('.btn-edit-group'); 
        if (!btn || !modal) return;
        if (!canEditUsers) {
          await fireSwal({
            icon: 'info',
            title: '<?= h(__('userList_error_title')) ?>',
            text: '<?= h(__('userList_err_no_permission')) ?>',
            confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
            confirmButtonColor: '#6c757d'
          });
          return;
        }

        hideErr();
        const userID  = btn.getAttribute('data-user-id');
        const nama    = btn.getAttribute('data-nama') || '-';
        const stafid  = btn.getAttribute('data-stafid') || '-';
        const displayId = btn.getAttribute('data-displayid') || stafid;
        const nopekerja = btn.getAttribute('data-nopekerja') || '';
        if (isProtectedStaffAccountClient(stafid) && !canSelfManageProtectedStaffAccountClient(stafid)) {
          await fireSwal({
            icon: 'info',
            title: '<?= h(__('userList_error_title')) ?>',
            text: '<?= h(__('userList_protected_self_manage_only')) ?>',
            confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
            confirmButtonColor: '#0d6efd'
          });
          return;
        }
        const avatarUrl = btn.getAttribute('data-avatar-url') || '';
        const jabatan = btn.getAttribute('data-jabatan') || '-';
        const gId     = btn.getAttribute('data-group-id') || '';
        const gKod    = btn.getAttribute('data-group-kod') || '';
        const scope   = (btn.getAttribute('data-scope') || 'staff').toLowerCase();
        const flag    = btn.getAttribute('data-flag') || '1';
        currentUserScope = scope || 'staff';
        configureEditModalForScope(currentUserScope);

        trackEvent('user_edit_group_open', { userID, currentGroupId: gId, currentGroup: gKod });

        document.getElementById('ug_userID').value = userID;
        document.getElementById('ug_scope').value = currentUserScope;
        document.getElementById('ug_userID').setAttribute('data-target-stafid', stafid);
        const $row = btn.closest('tr');
        const extraCount = parseInt($row?.getAttribute('data-extra-count') || '0', 10);
        const extraList = String($row?.getAttribute('data-extra-roles') || '').split(',').map(s=>s.trim()).filter(Boolean);
        setRoleButton(extraCount, extraList);
        document.getElementById('ug_nopekerja').value = nopekerja;
        currentPrimaryRoleName = (btn.getAttribute('data-group-name') || '').trim();
        
        // Store original values for comparison
        document.getElementById('ug_userID').setAttribute('data-original-group', gId);
        document.getElementById('ug_userID').setAttribute('data-original-flag', flag);
        
        const namaEl = document.getElementById('ug_nama');
        const jabatanEl = document.getElementById('ug_jabatan');
        const avatarEl = document.getElementById('ug_avatar');
        const flagEl = document.getElementById('ug_flag');
        const publicNameEl = document.getElementById('ug_publicName');
        const publicNicknameEl = document.getElementById('ug_publicNickname');
        const publicEmailEl = document.getElementById('ug_publicEmail');
        const publicPhoneEl = document.getElementById('ug_publicPhone');
        const publicUniversityEl = document.getElementById('ug_publicUniversity');
        const publicNoKpEl = document.getElementById('ug_publicNoKp');
        const publicPasswordEl = document.getElementById('ug_publicPassword');
        const publicPasswordConfirmEl = document.getElementById('ug_publicPasswordConfirm');

        if (namaEl) namaEl.textContent = `${nama} (${displayId})`;
        if (jabatanEl) jabatanEl.textContent = scope === 'public'
          ? (btn.getAttribute('data-email') || btn.getAttribute('data-loginid') || '-')
          : (jabatan || '-');
        if (flagEl) flagEl.value = flag;
        if (scope === 'public') {
          if (publicNameEl) publicNameEl.value = nama;
          if (publicNicknameEl) publicNicknameEl.value = btn.getAttribute('data-nickname') || '';
          if (publicEmailEl) publicEmailEl.value = btn.getAttribute('data-email') || btn.getAttribute('data-loginid') || '';
          if (publicPhoneEl) publicPhoneEl.value = btn.getAttribute('data-phone') || '';
          if (publicUniversityEl) publicUniversityEl.value = btn.getAttribute('data-university') || btn.getAttribute('data-jabatan') || '';
          if (publicNoKpEl) publicNoKpEl.value = btn.getAttribute('data-nokp') || '';
          if (publicPasswordEl) publicPasswordEl.value = '';
          if (publicPasswordConfirmEl) publicPasswordConfirmEl.value = '';
        }
        
        // Set avatar URL - guna URL dari User::getAvatarUrl() (PHP)
        if (avatarEl) {
          avatarEl.src = avatarUrl || '<?= base_url('assets/images/no-image.jpg') ?>';
        }

        await populateGroups(gId, currentUserScope);
        if (!currentPrimaryRoleName) {
          currentPrimaryRoleName = getPrimaryRoleNameFromSelect();
        }
        modal.show();
      });
    }

    // Open extra role modal
    document.getElementById('ug_addRoleBtn')?.addEventListener('click', async function(e){
      e.preventDefault();
      const userID = parseInt(document.getElementById('ug_userID').value || '0', 10);
      const currentStafId = document.getElementById('ug_userID')?.getAttribute('data-target-stafid') || '';
      if (isProtectedStaffAccountClient(currentStafId) && !canSelfManageProtectedStaffAccountClient(currentStafId)) {
        showRoleErr('<?= h(__('userList_protected_self_manage_only')) ?>');
        return;
      }
      if (!userID) {
        showErr('<?= h(__('userList_err_param')) ?>');
        return;
      }
      if (!roleModal && roleModalEl && window.bootstrap && bootstrap.Modal) {
        roleModal = new bootstrap.Modal(roleModalEl);
      }
      hideRoleErr();
      document.getElementById('re_userID').value = String(userID);
      const primaryName = currentPrimaryRoleName || getPrimaryRoleNameFromSelect() || '<?= h(__('userList_empty_value')) ?>';
      const primEl = document.getElementById('re_primaryRole');
      if (primEl) primEl.textContent = primaryName;
      await loadExtraRoles(userID);
      restoreParentUserGroupModal = !!(modalEl && modalEl.classList.contains('show'));
      if (restoreParentUserGroupModal && modal) {
        modalEl.addEventListener('hidden.bs.modal', function handleParentHiddenForRoleModal() {
          modalEl.removeEventListener('hidden.bs.modal', handleParentHiddenForRoleModal);
          roleModal?.show();
        }, { once: true });
        modal.hide();
      } else {
        roleModal?.show();
      }
    });

    // Save extra roles
    document.getElementById('roleExtraSaveBtn')?.addEventListener('click', createRateLimitedHandler(async function(){
      hideRoleErr();
      const userID = parseInt(document.getElementById('re_userID').value || '0', 10);
      if (!userID) {
        showRoleErr('<?= h(__('userList_err_param')) ?>');
        return;
      }
      const selected = Array.from(roleListEl?.querySelectorAll('input[type="checkbox"]:checked') || [])
        .map(el => parseInt(el.value || '0', 10))
        .filter(v => v > 0);

      const saveBtn = document.getElementById('roleExtraSaveBtn');
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> <?= h(__('userList_btn_saving')) ?>';

      try {
        const r = await fetch('<?= base_url('ajax/user-extra-roles.php') ?>', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF, 'Accept':'application/json'},
          body: JSON.stringify({ action: 'save', userID, roles: selected, scope: currentUserScope })
        });
        const j = await r.json();
        if (!r.ok || !j || j.error) throw new Error((j && j.message) || '<?= h(__('userList_err_update_group')) ?>');
        // Update row + button with current selections
        const selectedNames = Array.from(roleListEl?.querySelectorAll('input[type="checkbox"]:checked') || [])
          .map(el => el.parentElement?.querySelector('.role-label')?.textContent?.trim() || '')
          .filter(Boolean);
        updateUserRow(userID, { extraRoles: selectedNames });
        setRoleButton(selectedNames.length, selectedNames);
        restoreParentAfterRoleAlert = restoreParentUserGroupModal;
        restoreParentUserGroupModal = false;
        roleModal?.hide();
        if (window.Swal) {
          await fireSwal({
            icon: 'success',
            title: '<?= h(__('userList_success_title')) ?>',
            text: j.message || '<?= h(__('userList_success_update_roles')) ?>',
            confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
            confirmButtonColor: '#198754'
          });
        }
        if (restoreParentAfterRoleAlert && modal) {
          restoreParentAfterRoleAlert = false;
          modal.show();
        }
      } catch (e) {
        showRoleErr(e.message || '<?= h(__('userList_err_update_group')) ?>');
      } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      }
    }, 1000));

    if (roleModalEl) {
      roleModalEl.addEventListener('hidden.bs.modal', function() {
        if (restoreParentUserGroupModal && modalEl && modal) {
          restoreParentUserGroupModal = false;
          modal.show();
        }
        restoreParentUserGroupModal = false;
      });
    }

    // Helper function untuk validation dengan blink effect (modal edit)
    function validateFieldEdit(fieldElement, isValid) {
      if (!fieldElement) return;
      
      // Remove existing invalid class
      fieldElement.classList.remove('field-invalid');
      
      // If invalid, add blink effect
      if (!isValid) {
        fieldElement.classList.add('field-invalid');
        
        // Scroll to field if not visible
        setTimeout(() => {
          fieldElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
        
        // Remove class after animation
        setTimeout(() => {
          fieldElement.classList.remove('field-invalid');
        }, 1500);
      }
    }
    
    document.getElementById('ug_saveBtn')?.addEventListener('click', createRateLimitedHandler(async function(){
      hideErr();
      
      // Remove all invalid classes first
      document.querySelectorAll('#userGroupModal .field-invalid').forEach(el => {
        el.classList.remove('field-invalid');
      });
      
      const userID   = parseInt(document.getElementById('ug_userID').value || '0', 10);
      const targetStafId = document.getElementById('ug_userID').getAttribute('data-target-stafid') || '';
      const groupID = document.getElementById('ug_groupKod').value || '';
      const flag     = parseInt(document.getElementById('ug_flag').value || '1', 10);
      const editScope = String(document.getElementById('ug_scope')?.value || currentUserScope || 'staff').toLowerCase();
      if (isProtectedStaffAccountClient(targetStafId) && !canSelfManageProtectedStaffAccountClient(targetStafId)) {
        showErr('<?= h(__('userList_protected_self_manage_only')) ?>');
        return;
      }
      
      // Validation dengan blink effect
      let isValid = true;
      
      // Validate userID
      if (!userID) {
        showErr('<?= h(__('userList_err_param')) ?>');
        return;
      }
      
      // Validate Group dengan validateGroupId function
      const groupSelect = document.getElementById('ug_groupKod');
      if (!groupID || groupID === '' || !validateGroupId(groupID)) {
        validateFieldEdit(groupSelect, false);
        isValid = false;
      } else {
        groupSelect.classList.remove('field-invalid');
      }
      
      if (!isValid) {
        showErr(editScope === 'public' ? '<?= h(__('userList_err_update_public')) ?>' : '<?= h(__('userList_err_update_group')) ?>');
        return; // Stop submission if validation fails
      }
      
      // Get original values
      const originalGroup = document.getElementById('ug_userID').getAttribute('data-original-group') || '';
      const originalFlag = parseInt(document.getElementById('ug_userID').getAttribute('data-original-flag') || '1', 10);
      
      // Check if anything changed
      const groupChanged = (String(groupID) !== String(originalGroup));
      const flagChanged = (flag !== originalFlag);

      // Disable button during request
      const saveBtn = document.getElementById('ug_saveBtn');
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> <?= h(__('userList_btn_saving')) ?>';

      try{
        let url = '<?= base_url('ajax/user-set-group.php') ?>';
        let requestBody = { userID };
        if (groupID) {
          requestBody.groupID = parseInt(groupID, 10);
        }
        requestBody.flag = flag;

        if (editScope === 'public') {
          const publicNameEl = document.getElementById('ug_publicName');
          const publicNicknameEl = document.getElementById('ug_publicNickname');
          const publicEmailEl = document.getElementById('ug_publicEmail');
          const publicPhoneEl = document.getElementById('ug_publicPhone');
          const publicUniversityEl = document.getElementById('ug_publicUniversity');
          const publicNoKpEl = document.getElementById('ug_publicNoKp');
          const publicPasswordEl = document.getElementById('ug_publicPassword');
          const publicPasswordConfirmEl = document.getElementById('ug_publicPasswordConfirm');

          const publicName = String(publicNameEl?.value || '').trim();
          const publicNickname = String(publicNicknameEl?.value || '').trim();
          const publicEmail = String(publicEmailEl?.value || '').trim().toLowerCase();
          const publicPhone = String(publicPhoneEl?.value || '').trim();
          const publicUniversity = String(publicUniversityEl?.value || '').trim();
          const publicNoKp = String(publicNoKpEl?.value || '').trim();
          const publicPassword = String(publicPasswordEl?.value || '');
          const publicPasswordConfirm = String(publicPasswordConfirmEl?.value || '');

          let publicValid = true;
          if (!publicName) {
            validateFieldEdit(publicNameEl, false);
            publicValid = false;
          }
          if (!validateEmailAddress(publicEmail)) {
            validateFieldEdit(publicEmailEl, false);
            publicValid = false;
          }
          if (publicPassword !== '' && publicPassword.length < 6) {
            validateFieldEdit(publicPasswordEl, false);
            publicValid = false;
          }
          if (publicPassword !== '' || publicPasswordConfirm !== '') {
            if (publicPasswordConfirm !== publicPassword) {
              validateFieldEdit(publicPasswordConfirmEl, false);
              publicValid = false;
            }
          }
          if (!publicValid) {
            showErr('<?= h(__('userList_err_update_public')) ?>');
            return;
          }

          url = '<?= base_url('ajax/user-update-public.php') ?>';
          requestBody = {
            userID,
            groupID: groupID ? parseInt(groupID, 10) : 0,
            flag,
            name: publicName,
            nickname: publicNickname,
            email: publicEmail,
            phone: publicPhone,
            university: publicUniversity,
            nokp: publicNoKp,
            password: publicPassword,
            password_confirm: publicPasswordConfirm
          };
        } else {
          const resetPasswordEl = document.getElementById('ug_resetPassword');
          const resetPasswordConfirmEl = document.getElementById('ug_resetPasswordConfirm');
          const resetPassword = String(resetPasswordEl?.value || '');
          const resetPasswordConfirm = String(resetPasswordConfirmEl?.value || '');

          let passwordValid = true;
          if (resetPassword !== '' && resetPassword.length < 6) {
            validateFieldEdit(resetPasswordEl, false);
            passwordValid = false;
          }
          if (resetPassword !== '' || resetPasswordConfirm !== '') {
            if (resetPasswordConfirm !== resetPassword) {
              validateFieldEdit(resetPasswordConfirmEl, false);
              passwordValid = false;
            }
          }
          if (!passwordValid) {
            showErr('<?= h(__('userList_err_update_group')) ?>');
            return;
          }

          requestBody.password = resetPassword;
          requestBody.password_confirm = resetPasswordConfirm;
        }

        trackEvent(editScope === 'public' ? 'user_edit_public_save' : 'user_edit_group_save', { userID, groupID: parseInt(groupID, 10), flag });
        
        const r = await fetchWithRetry(url, {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF, 'Accept':'application/json'},
          body: JSON.stringify(requestBody)
        });
        
        // Check if response is OK
        if (!r.ok) {
          let errorMsg = '<?= h(__('userList_err_update_group')) ?>';
          try {
            const errorData = await r.json();
            if (errorData && errorData.message) {
              errorMsg = errorData.message;
            }
          } catch (e) {
            // If JSON parsing fails, use status text
            errorMsg = `<?= h(__('userList_http_status_prefix')) ?> ${r.status}: ${r.statusText || '<?= h(__('userList_err_server')) ?>'}`;
          }
          throw new Error(errorMsg);
        }
        
        // Parse JSON response
        let j;
        try {
          j = await r.json();
        } catch (e) {
          throw new Error('<?= h(__('userList_err_invalid_json')) ?>');
        }
        
        if (!j || j.error){
          throw new Error((j && j.message) || (editScope === 'public' ? '<?= h(__('userList_err_update_public')) ?>' : '<?= h(__('userList_err_update_group')) ?>'));
        }

        trackEvent(editScope === 'public' ? 'user_edit_public_success' : 'user_edit_group_success', { userID, groupID: parseInt(groupID, 10), flag });

        // Close modal first
        modal?.hide();
        
        // Try to update row in-place first (optimized)
        try {
          // Extract groupName from response - check both j.groupName and j.group.nama
          const groupIdResp = j.group && (j.group.id || j.group.f_groupID) ? (j.group.id || j.group.f_groupID) : parseInt(groupID, 10);
          const groupKodResp = j.group && (j.group.kod || j.group.f_groupKod) ? (j.group.kod || j.group.f_groupKod) : '';
          const groupName = j.groupName || (j.group && j.group.nama) || groupKodResp || groupID;
          const rowUpdateData = {
            groupID: groupIdResp,
            groupKod: groupKodResp,
            groupName: groupName,
            flag: flag
          };
          if (editScope === 'public') {
            rowUpdateData.name = j.user?.name || requestBody.name || '';
            rowUpdateData.loginID = j.user?.loginID || requestBody.email || '';
            rowUpdateData.nickname = j.user?.nickname || requestBody.nickname || '';
            rowUpdateData.email = j.user?.email || requestBody.email || '';
            rowUpdateData.phone = j.user?.phone || requestBody.phone || '';
            rowUpdateData.university = j.user?.university || requestBody.university || '';
            rowUpdateData.nokp = j.user?.nokp || requestBody.nokp || '';
            rowUpdateData.jabatan = j.user?.university || requestBody.university || '';
          }
          updateUserRow(userID, rowUpdateData);
        } catch (e) {
          // Fallback to full reload if in-place update fails
          await reloadUserTable(userID);
        }
        
        // Show success message with SweetAlert
        if (window.Swal) {
          await fireSwal({
            icon: 'success',
            title: '<?= h(__('userList_success_title')) ?>',
            text: (j.message || (editScope === 'public' ? '<?= h(__('userList_success_update_public')) ?>' : '<?= h(__('userList_success_update_group')) ?>')),
            confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
            confirmButtonColor: '#198754',
            timer: 2000,
            timerProgressBar: true
          });
        }
      }catch(e){
        // Better error handling - sanitize error message
        const errorMsg = sanitizeError(e);
        trackEvent(editScope === 'public' ? 'user_edit_public_error' : 'user_edit_group_error', { userID, error: errorMsg });
        showErr(errorMsg);
      } finally {
        // Re-enable button
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalText;
        }
      }
    }, 1000));

    // ===== Modal Tambah Pengguna =====
    const addUserModalEl = document.getElementById('addUserModal');
    const auStafSelect = document.getElementById('au_stafSelect');
    const auErrorEl = document.getElementById('au_error');
    let currentStudentSelection = null;

    function resetAddModalInfoCard() {
      const jabatanEl = document.getElementById('au_jabatan');
      const jawatanEl = document.getElementById('au_jawatan');
      const extraInfo1El = document.getElementById('au_extraInfo1');
      const extraInfo2El = document.getElementById('au_extraInfo2');
      const extraInfo1Wrap = document.getElementById('au_extraInfo1Wrap');
      const extraInfo2Wrap = document.getElementById('au_extraInfo2Wrap');

      if (jabatanEl) {
        jabatanEl.textContent = '<?= h(__('userList_empty_value')) ?>';
        jabatanEl.className = 'info-value';
      }
      if (jawatanEl) {
        jawatanEl.textContent = '<?= h(__('userList_empty_value')) ?>';
        jawatanEl.className = 'info-value';
      }
      if (extraInfo1El) {
        extraInfo1El.textContent = '<?= h(__('userList_empty_value')) ?>';
        extraInfo1El.className = 'info-value';
      }
      if (extraInfo2El) {
        extraInfo2El.textContent = '<?= h(__('userList_empty_value')) ?>';
        extraInfo2El.className = 'info-value';
      }
      if (extraInfo1Wrap) extraInfo1Wrap.style.display = 'none';
      if (extraInfo2Wrap) extraInfo2Wrap.style.display = 'none';
    }

    function resetPublicFormFields() {
      const ids = [
        'au_publicName',
        'au_publicNickname',
        'au_publicEmail',
        'au_publicPhone',
        'au_publicUniversity',
        'au_publicNoKp',
        'au_publicPassword',
        'au_publicPasswordConfirm'
      ];
      ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
          el.value = '';
          el.classList.remove('field-invalid');
        }
      });
    }

    function configureAddModalForScope(scope) {
      const normalized = String(scope || 'staff').trim().toLowerCase() || 'staff';
      const titleEl = document.getElementById('addUserModalTitle');
      const saveBtn = document.getElementById('au_saveBtn');
      const infoTabLabelEl = document.getElementById('au_infoTabLabel');
      const sectionTitleEl = document.getElementById('au_sectionTitle');
      const selectLabelEl = document.getElementById('au_selectLabel');
      const primaryInfoLabelEl = document.getElementById('au_primaryInfoLabel');
      const secondaryInfoLabelEl = document.getElementById('au_secondaryInfoLabel');
      const extraInfo1LabelEl = document.getElementById('au_extraInfo1Label');
      const extraInfo2LabelEl = document.getElementById('au_extraInfo2Label');
      const extraInfo1Wrap = document.getElementById('au_extraInfo1Wrap');
      const extraInfo2Wrap = document.getElementById('au_extraInfo2Wrap');
      const staffSelectWrap = document.getElementById('au_staffSelectWrap');
      const infoCard = document.getElementById('au_infoCard');
      const publicFormSection = document.getElementById('au_publicFormSection');

      if (staffSelectWrap) staffSelectWrap.classList.remove('d-none');
      if (infoCard) infoCard.classList.remove('d-none');
      if (publicFormSection) publicFormSection.classList.add('d-none');

      if (normalized === 'student') {
        if (titleEl) titleEl.innerHTML = '<i class="ri-user-add-line me-2"></i> <?= h(__('userList_modal_add_student_title')) ?>';
        if (saveBtn) saveBtn.innerHTML = '<i class="ri-user-add-line me-1"></i> <?= h(__('userList_modal_add_student_title')) ?>';
        if (infoTabLabelEl) infoTabLabelEl.textContent = '<?= h(__('userList_modal_section_student_info')) ?>';
        if (sectionTitleEl) sectionTitleEl.innerHTML = '<i class="ri-user-star-line me-1"></i> <?= h(__('userList_modal_section_student_info')) ?>';
        if (selectLabelEl) selectLabelEl.innerHTML = '<i class="ri-graduation-cap-line"></i> <?= h(__('userList_modal_label_student')) ?> <span class="text-danger">*</span>';
        if (primaryInfoLabelEl) primaryInfoLabelEl.textContent = '<?= h(__('userList_modal_label_faculty')) ?>';
        if (secondaryInfoLabelEl) secondaryInfoLabelEl.textContent = '<?= h(__('userList_modal_label_program')) ?>';
        if (extraInfo1LabelEl) extraInfo1LabelEl.textContent = '<?= h(__('userList_modal_label_level')) ?>';
        if (extraInfo2LabelEl) extraInfo2LabelEl.textContent = '<?= h(__('userList_modal_label_status_category')) ?>';
        if (extraInfo1Wrap) extraInfo1Wrap.style.display = '';
        if (extraInfo2Wrap) extraInfo2Wrap.style.display = '';
        if (auStafSelect) {
          auStafSelect.dataset.placeholder = '<?= h(__('userList_modal_placeholder_select_student')) ?>';
          auStafSelect.setAttribute('data-placeholder', '<?= h(__('userList_modal_placeholder_select_student')) ?>');
        }
        return;
      }

      if (normalized === 'public') {
        if (titleEl) titleEl.innerHTML = '<i class="ri-user-add-line me-2"></i> <?= h(__('userList_modal_add_public_title')) ?>';
        if (saveBtn) saveBtn.innerHTML = '<i class="ri-user-add-line me-1"></i> <?= h(__('userList_modal_btn_add')) ?>';
        if (infoTabLabelEl) infoTabLabelEl.textContent = '<?= h(__('userList_modal_section_public_info')) ?>';
        if (sectionTitleEl) sectionTitleEl.innerHTML = '<i class="ri-user-star-line me-1"></i> <?= h(__('userList_modal_section_public_info')) ?>';
        if (staffSelectWrap) staffSelectWrap.classList.add('d-none');
        if (infoCard) infoCard.classList.add('d-none');
        if (publicFormSection) publicFormSection.classList.remove('d-none');
        resetPublicFormFields();
        return;
      }

      if (titleEl) titleEl.innerHTML = '<i class="ri-user-add-line me-2"></i> <?= h(__('userList_modal_add_title')) ?>';
      if (saveBtn) saveBtn.innerHTML = '<i class="ri-user-add-line me-1"></i> <?= h(__('userList_modal_btn_add')) ?>';
      if (infoTabLabelEl) infoTabLabelEl.textContent = '<?= h(__('userList_modal_section_staff_info')) ?>';
      if (sectionTitleEl) sectionTitleEl.innerHTML = '<i class="ri-user-line me-1"></i> <?= h(__('userList_modal_section_staff_info')) ?>';
      if (selectLabelEl) selectLabelEl.innerHTML = '<i class="ri-user-line"></i> <?= h(__('userList_modal_label_staff')) ?> <span class="text-danger">*</span>';
      if (primaryInfoLabelEl) primaryInfoLabelEl.textContent = '<?= h(__('userList_modal_label_department')) ?>';
      if (secondaryInfoLabelEl) secondaryInfoLabelEl.textContent = '<?= h(__('userList_modal_label_position')) ?>';
      if (extraInfo1Wrap) extraInfo1Wrap.style.display = 'none';
      if (extraInfo2Wrap) extraInfo2Wrap.style.display = 'none';
      if (auStafSelect) {
        auStafSelect.dataset.placeholder = '<?= h(__('userList_modal_placeholder_select_staff')) ?>';
        auStafSelect.setAttribute('data-placeholder', '<?= h(__('userList_modal_placeholder_select_staff')) ?>');
      }
    }

    function updateAddModalInfoFromSelection(scope, payload = null) {
      const normalized = String(scope || 'staff').trim().toLowerCase() || 'staff';
      const jabatanEl = document.getElementById('au_jabatan');
      const jawatanEl = document.getElementById('au_jawatan');
      const extraInfo1El = document.getElementById('au_extraInfo1');
      const extraInfo2El = document.getElementById('au_extraInfo2');

      if (normalized === 'student') {
        if (jabatanEl) jabatanEl.textContent = payload?.fakulti || '<?= h(__('userList_empty_value')) ?>';
        if (jawatanEl) jawatanEl.textContent = payload?.program || '<?= h(__('userList_empty_value')) ?>';
        if (extraInfo1El) extraInfo1El.textContent = payload?.tahap_pengajian || '<?= h(__('userList_empty_value')) ?>';
        if (extraInfo2El) extraInfo2El.textContent = payload?.statuskategori || '<?= h(__('userList_empty_value')) ?>';
        return;
      }

      if (jabatanEl) jabatanEl.textContent = payload?.jabatan || '<?= h(__('userList_empty_value')) ?>';
      if (jawatanEl) jawatanEl.textContent = payload?.jawatan || '<?= h(__('userList_empty_value')) ?>';
    }

    async function fetchStudentDetailForModal(matrik) {
      const normalizedMatrik = String(matrik || '').trim();
      if (!normalizedMatrik) {
        return null;
      }

      const body = new URLSearchParams();
      body.set('matrik', normalizedMatrik);
      body.set('csrf_token', CSRF);

      const response = await fetch('<?= base_url('ajax/user-student-detail.php') ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-CSRF-Token': CSRF,
          'Accept': 'application/json'
        },
        body: body.toString()
      });

      const text = await response.text();
      let data = null;
      try {
        data = JSON.parse(text);
      } catch (e) {
        return null;
      }

      if (!response.ok || !data || data.error || !data.student) {
        return null;
      }

      return data.student;
    }

    async function enrichStudentSelectionForModal(selection) {
      if (currentAddScope !== 'student') {
        return;
      }

      const matrik = String(selection?.matrik || selection?.id || '').trim();
      if (!matrik) {
        return;
      }

      const hasCoreDetails = (
        String(selection?.fakulti || '').trim() !== '' &&
        String(selection?.program || '').trim() !== '' &&
        String(selection?.tahap_pengajian || '').trim() !== '' &&
        String(selection?.statuskategori || '').trim() !== ''
      );

      if (hasCoreDetails) {
        updateAddModalInfoFromSelection('student', selection);
        return;
      }

      const detail = await fetchStudentDetailForModal(matrik);
      if (!detail) {
        updateAddModalInfoFromSelection('student', selection);
        return;
      }

      currentStudentSelection = {
        ...(currentStudentSelection || {}),
        ...(selection || {}),
        ...detail
      };

      if (String(currentStudentSelection.id || currentStudentSelection.matrik || '') !== matrik) {
        currentStudentSelection.id = matrik;
      }

      updateAddModalInfoFromSelection('student', currentStudentSelection);
    }

    async function prepareAddUserModalForScope(scope) {
      const normalized = String(scope || 'staff').trim().toLowerCase() || 'staff';
      currentAddScope = normalized;
      const groupSelect = document.getElementById('au_groupKod');
      currentStudentSelection = null;
      configureAddModalForScope(normalized);
      resetAddModalInfoCard();
      resetPublicFormFields();
      await populateSelectGroupsForScope(groupSelect, normalized);
    }
    
    function showAuErr(msg) {
      if (!auErrorEl) return;
      auErrorEl.textContent = msg || '<?= h(__('userList_err_unknown')) ?>';
      auErrorEl.classList.remove('d-none');
    }
    
    function hideAuErr() {
      if (!auErrorEl) return;
      auErrorEl.classList.add('d-none');
    }

    async function openAddUserModalForScope(scope) {
      const normalized = String(scope || 'staff').trim().toLowerCase() || 'staff';
      currentAddScope = normalized;
      configureAddModalForScope(normalized);
      resetAddModalInfoCard();
      resetPublicFormFields();

      const modalTarget = document.getElementById('addUserModal');
      if (!modalTarget || !window.bootstrap || !bootstrap.Modal) {
        return;
      }

      const modal = bootstrap.Modal.getOrCreateInstance(modalTarget);
      modal.show();
      showAddModalTab('#au-info-tab');

      window.setTimeout(function() {
        prepareAddUserModalForScope(normalized).catch(function() {
          hideAuErr();
        });
      }, 0);
    }

    window.userListOpenAdd = function(scope) {
      return openAddUserModalForScope(scope);
    };
    window.openAddUserModalForScope = openAddUserModalForScope;

    function showAddModalTab(tabSelector) {
      const tabTrigger = document.querySelector(tabSelector);
      if (!tabTrigger || !window.bootstrap || !bootstrap.Tab) {
        return;
      }
      bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
    }
    
    // Handle focus + reset when modal hides
    if (addUserModalEl) {
      // Before hiding: ensure no element inside modal keeps focus (fixes aria-hidden warning)
      addUserModalEl.addEventListener('hide.bs.modal', function() {
        try {
          const active = document.activeElement;
          if (active && addUserModalEl.contains(active)) {
            // Blur focused element inside modal so it isn't hidden from AT
            active.blur();
          }
        } catch (e) { /* ignore */ }

        try {
          // Close Select2 dropdown if open to prevent focus retention
          if (window.jQuery && auStafSelect && jQuery(auStafSelect).data('select2')) {
            jQuery(auStafSelect).select2('close');
          }
        } catch (e) { /* ignore */ }

        try {
          // Return focus to the Add User button or a sensible fallback
          const trigger =
            document.getElementById('btnAddUser') ||
            document.getElementById('btnAddUserStudent') ||
            document.getElementById('btnAddUserPublic') ||
            document.querySelector('[data-bs-target="#addUserModal"]');
          if (trigger) trigger.focus(); else document.body.focus();
        } catch (e) { /* ignore */ }
      });

      // Reset form when modal is fully hidden
      addUserModalEl.addEventListener('hidden.bs.modal', function() {
        currentAddScope = 'staff';
        currentStudentSelection = null;
        if (auStafSelect) {
          if (window.jQuery && jQuery(auStafSelect).data('select2')) {
            jQuery(auStafSelect).val(null).trigger('change');
          } else {
            auStafSelect.value = '';
          }
        }
        document.getElementById('au_groupKod').value = '';
        document.getElementById('au_flag').value = '1';
        configureAddModalForScope('staff');
        showAddModalTab('#au-info-tab');
        resetAddModalInfoCard();
        resetPublicFormFields();
        hideAuErr();
      });
    }
    
    // Initialize Select2 untuk dropdown staf (simple, tanpa retry loop)
    function initSelect2ForModal() {
      jQuery(function($) {
        if (typeof $.fn.select2 === 'undefined') {
          return;
        }

        // Setup Select2 dengan lazy loading staf list bila modal dibuka
        if (addUserModalEl && auStafSelect) {
          addUserModalEl.addEventListener('shown.bs.modal', async function() {
            const $sel = $(auStafSelect);
            const placeholderText = currentAddScope === 'student'
              ? '<?= h(__('userList_modal_placeholder_select_student')) ?>'
              : ((currentAddScope === 'public')
                ? '<?= h(__('userList_modal_placeholder_select_public')) ?>'
                : '<?= h(__('userList_modal_placeholder_select_staff')) ?>');

            if (currentAddScope === 'public') {
              if ($sel.data('select2')) {
                $sel.select2('destroy');
              }
              if (auStafSelect) {
                auStafSelect.innerHTML = '<option value=""></option>';
                auStafSelect.value = '';
              }
              return;
            }

            // Destroy existing instance jika ada
            if ($sel.data('select2')) {
              $sel.select2('destroy');
            }

            currentStudentSelection = null;

            // Lazy load options ikut scope semasa
            // Helper: safe innerHTML setter (prefer DOMPurify when available)
            function setSafeInnerHTML(el, html) {
              if (!el) return;
              if (!html) { el.innerHTML = ''; return; }
              if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
                el.innerHTML = DOMPurify.sanitize(html);
                return;
              }
              try {
                var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
                doc.querySelectorAll('script').forEach(function(s){ s.remove(); });
                doc.querySelectorAll('*').forEach(function(n){
                  Array.from(n.attributes).forEach(function(a){
                    if (/^on/i.test(a.name)) n.removeAttribute(a.name);
                    if ((a.name === 'src' || a.name === 'href') && /^javascript:/i.test(a.value)) n.removeAttribute(a.name);
                  });
                });
                el.innerHTML = doc.body.firstChild ? doc.body.firstChild.innerHTML : '';
              } catch (e) {
                el.innerHTML = html;
              }
            }

            function ensureStaffPlaceholder() {
              if (!auStafSelect) return;
              const first = auStafSelect.options[0];
              if (!first || first.value !== '') {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = placeholderText;
                auStafSelect.insertBefore(opt, auStafSelect.firstChild);
              }
            }

            function bindSelect2SearchField(searchPlaceholder) {
              $sel.off('select2:open.userListSearch').on('select2:open.userListSearch', function() {
                window.requestAnimationFrame(function() {
                  const searchField = addUserModalEl.querySelector(
                    '.select2-container--open .select2-search--dropdown .select2-search__field'
                  );
                  if (!searchField) return;
                  searchField.setAttribute('placeholder', searchPlaceholder);
                  searchField.setAttribute('aria-label', searchPlaceholder);
                  searchField.focus();
                });
              });
            }

            function syncSelect2Placeholder(selectionPlaceholder) {
              // jQuery menyimpan nilai data-* pertama dalam cache. Apabila modal
              // bertukar staf -> pelajar, buang cache lama dan selaraskan teks
              // selection yang telah dirender oleh Select2.
              $sel.removeData('placeholder');
              $sel.attr('data-placeholder', selectionPlaceholder);
              const placeholderEl = $sel.next('.select2-container')
                .find('.select2-selection__placeholder');
              if (placeholderEl.length) {
                placeholderEl.text(selectionPlaceholder).attr('title', selectionPlaceholder);
              }
            }

            if (currentAddScope === 'student') {
              auStafSelect.innerHTML = '<option value=""></option>';
              $sel.select2({
                width: '100%',
                allowClear: true,
                placeholder: placeholderText,
                minimumInputLength: 2,
                dropdownParent: $(addUserModalEl),
                ajax: {
                  url: '<?= base_url('ajax/user-list-student-options.php') ?>',
                  type: 'POST',
                  dataType: 'json',
                  delay: 250,
                  headers: {
                    'X-CSRF-Token': CSRF,
                    'Accept': 'application/json'
                  },
                  data: function(params) {
                    return {
                      q: params.term || '',
                      page: params.page || 1,
                      csrf_token: CSRF
                    };
                  },
                  processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                      results: Array.isArray(data?.results) ? data.results : [],
                      pagination: data?.pagination || { more: false }
                    };
                  }
                }
              });
              bindSelect2SearchField(placeholderText);
              $sel.val(null).trigger('change');
              syncSelect2Placeholder(placeholderText);
              return;
            }

            try {
              auStafSelect.innerHTML = '<option value=""><?= h(__('userList_loading_staff')) ?>...</option>';

              const r = await fetch('<?= base_url('ajax/user-list-staf-options.php') ?>', {
                headers: { 'Accept': 'application/json' }
              });

              if (r.ok) {
                const text = await r.text();
                let j = null;
                try {
                  j = JSON.parse(text);
                } catch (pe) {
                  auStafSelect.innerHTML = '<option value=""><?= h(__('userList_err_invalid_response')) ?></option>';
                }

                if (j) {
                  if (!j.error && Array.isArray(j.options) && j.options.length > 0) {
                    auStafSelect.innerHTML = '';
                    ensureStaffPlaceholder();
                    j.options.forEach(opt => {
                      try {
                        const option = document.createElement('option');
                        option.value = opt.value || '';
                        option.setAttribute('data-idpekerja', opt.idpekerja || '');
                        option.setAttribute('data-nama', opt.nama || '');
                        option.setAttribute('data-jawatan', opt.jawatan || '');
                        option.setAttribute('data-jabatan', opt.jabatan || '');
                        if (opt.disabled) option.disabled = true;
                        option.textContent = opt.display || opt.nama || opt.value || '';
                        auStafSelect.appendChild(option);
                      } catch (e) { /* ignore malformed option */ }
                    });
                  } else if (!j.error && j.html) {
                    setSafeInnerHTML(auStafSelect, j.html || '');
                    ensureStaffPlaceholder();
                  } else {
                    auStafSelect.innerHTML = '<option value=""><?= h(__('userList_err_load_staff')) ?></option>';
                  }
                }
              } else {
                auStafSelect.innerHTML = '<option value=""><?= h(__('userList_err_load_staff')) ?></option>';
              }
            } catch (e) {
              auStafSelect.innerHTML = '<option value=""><?= h(__('userList_err_load_staff')) ?></option>';
            }

            if (auStafSelect) {
              auStafSelect.value = '';
            }

            $sel.select2({
              width: '100%',
              allowClear: true,
              placeholder: placeholderText,
              minimumResultsForSearch: 0,
              dropdownParent: $(addUserModalEl)
            });
            bindSelect2SearchField(placeholderText);
            $sel.val('').trigger('change');
            syncSelect2Placeholder(placeholderText);
          });

          $(auStafSelect).on('select2:select', function(e) {
            if (currentAddScope !== 'student') {
              return;
            }
            currentStudentSelection = e?.params?.data || null;
            updateAddModalInfoFromSelection('student', currentStudentSelection);
            enrichStudentSelectionForModal(currentStudentSelection).catch(function() {});
          });

          // Auto isi info bila pilih rekod
          $(auStafSelect).on('change', function() {
            if (currentAddScope === 'student') {
              if (!this.value) {
                currentStudentSelection = null;
                resetAddModalInfoCard();
                configureAddModalForScope('student');
                return;
              }

              if (currentStudentSelection && String(currentStudentSelection.id || '') === String(this.value || '')) {
                updateAddModalInfoFromSelection('student', currentStudentSelection);
                enrichStudentSelectionForModal(currentStudentSelection).catch(function() {});
              }
              return;
            }

            const opt = this.selectedOptions && this.selectedOptions[0]
              ? this.selectedOptions[0]
              : null;
            const jabatan = opt ? (opt.getAttribute('data-jabatan') || '') : '';
            const jawatan = opt ? (opt.getAttribute('data-jawatan') || '') : '';

            const jabatanEl = document.getElementById('au_jabatan');
            const jawatanEl = document.getElementById('au_jawatan');
            const auInfoCard = document.getElementById('au_infoCard');

            if (jabatanEl) {
              jabatanEl.textContent = jabatan || '<?= h(__('userList_empty_value')) ?>';
            }
            if (jawatanEl) {
              jawatanEl.textContent = jawatan || '<?= h(__('userList_empty_value')) ?>';
            }

            // Pastikan info card sentiasa visible
            if (auInfoCard) {
              auInfoCard.style.display = 'block';
            }
          });
        }
      });
    }
    
    // Function untuk refresh dropdown staf selepas delete/tambah user
    async function refreshStafDropdown() {
      const auStafSelect = document.getElementById('au_stafSelect');
      if (!auStafSelect) return;
      const placeholderText = auStafSelect.getAttribute('data-placeholder') || '<?= h(__('userList_modal_placeholder_select_staff')) ?>';

      function ensureStaffPlaceholder() {
        const first = auStafSelect.options[0];
        if (!first || first.value !== '') {
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = placeholderText;
          auStafSelect.insertBefore(opt, auStafSelect.firstChild);
        }
      }
      
      try {
        // Fetch staf list terkini dari server dengan retry
        const r = await fetchWithRetry('<?= base_url('ajax/user-list-staf-options.php') ?>', {
          headers: { 'Accept': 'application/json' }
        });

        if (!r.ok) return;

        const text = await r.text();
        let j = null;
        try {
          j = JSON.parse(text);
        } catch (pe) {
          return; // silently ignore malformed response
        }
        if (j && j.error) return;

        // Destroy Select2 jika sudah initialized
        const $sel = jQuery(auStafSelect);
        if ($sel.data('select2')) {
          $sel.select2('destroy');
        }

        // Populate options prefer structured data
        if (Array.isArray(j.options) && j.options.length > 0) {
          auStafSelect.innerHTML = '';
          ensureStaffPlaceholder();
          j.options.forEach(opt => {
            try {
              const option = document.createElement('option');
              option.value = opt.value || '';
              option.setAttribute('data-idpekerja', opt.idpekerja || '');
              option.setAttribute('data-nama', opt.nama || '');
              option.setAttribute('data-jawatan', opt.jawatan || '');
              option.setAttribute('data-jabatan', opt.jabatan || '');
              if (opt.disabled) option.disabled = true;
              option.textContent = opt.display || opt.nama || opt.value || '';
              auStafSelect.appendChild(option);
            } catch (e) { /* ignore malformed option */ }
          });
        } else if (j.html) {
          auStafSelect.innerHTML = j.html || '';
          ensureStaffPlaceholder();
        } else {
          return;
        }
        auStafSelect.value = '';
        
        // Re-init Select2 jika modal sedang dibuka
        const addUserModalEl = document.getElementById('addUserModal');
        if (addUserModalEl && bootstrap.Modal.getInstance(addUserModalEl)?.isShown) {
          $sel.select2({
            width: '100%',
            allowClear: true,
            placeholder: placeholderText,
            minimumResultsForSearch: 0,
            dropdownParent: jQuery(addUserModalEl)
          });
          $sel.off('select2:open.userListSearch').on('select2:open.userListSearch', function() {
            window.requestAnimationFrame(function() {
              const searchField = addUserModalEl.querySelector(
                '.select2-container--open .select2-search--dropdown .select2-search__field'
              );
              if (!searchField) return;
              searchField.setAttribute('placeholder', placeholderText);
              searchField.setAttribute('aria-label', placeholderText);
              searchField.focus();
            });
          });
          $sel.val('').trigger('change');
        }
      } catch (e) {
        // Silently ignore refresh errors to avoid noisy console in production
      }
    }
    
    // Tunggu semua skrip defer siap supaya Select2 menggunakan instance jQuery terakhir.
    if (document.readyState === 'complete') {
      initSelect2ForModal();
    } else {
      window.addEventListener('load', initSelect2ForModal, { once: true });
    }

    document.addEventListener('click', async function(e) {
      const addBtn = e.target.closest('#btnAddUser, #btnAddUserStudent, #btnAddUserPublic');
      if (!addBtn) return;
      if (addBtn.dataset.modalBound === '1') return;
      e.preventDefault();
      const scope = addBtn.id === 'btnAddUserStudent'
        ? 'student'
        : (addBtn.id === 'btnAddUserPublic' ? 'public' : 'staff');
      if (window.userListOpenAdd) {
        await window.userListOpenAdd(scope);
      }
    });
    
    // Helper function untuk validation dengan blink effect
    function validateField(fieldElement, isValid) {
      if (!fieldElement) return;
      
      // Remove existing invalid class
      fieldElement.classList.remove('field-invalid');
      
      // If invalid, add blink effect
      if (!isValid) {
        fieldElement.classList.add('field-invalid');
        
        // Scroll to field if not visible
        setTimeout(() => {
          fieldElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
        
        // Remove class after animation
        setTimeout(() => {
          fieldElement.classList.remove('field-invalid');
        }, 1500);
      }
    }
    
    // Save button handler untuk Add User
    document.getElementById('au_saveBtn')?.addEventListener('click', createRateLimitedHandler(async function() {
      hideAuErr();
      
      // Remove all invalid classes first
      document.querySelectorAll('#addUserModal .field-invalid').forEach(el => {
        el.classList.remove('field-invalid');
      });
      
      const selectedIdentifier = auStafSelect ? auStafSelect.value : '';
      const groupID = document.getElementById('au_groupKod').value || '';
      const flag = parseInt(document.getElementById('au_flag').value || '1', 10);
      const publicNameEl = document.getElementById('au_publicName');
      const publicNicknameEl = document.getElementById('au_publicNickname');
      const publicEmailEl = document.getElementById('au_publicEmail');
      const publicPhoneEl = document.getElementById('au_publicPhone');
      const publicUniversityEl = document.getElementById('au_publicUniversity');
      const publicNoKpEl = document.getElementById('au_publicNoKp');
      const publicPasswordEl = document.getElementById('au_publicPassword');
      const publicPasswordConfirmEl = document.getElementById('au_publicPasswordConfirm');
      
      // Get idpekerja from selected option
      let idpekerja = '';
      let selectedOption = null;
      if (auStafSelect && auStafSelect.selectedOptions && auStafSelect.selectedOptions[0]) {
        selectedOption = auStafSelect.selectedOptions[0];
        idpekerja = selectedOption.getAttribute('data-idpekerja') || '';
      }
      
      // Validation dengan blink effect
      let isValid = true;
      
      let publicPayload = null;
      if (currentAddScope === 'public') {
        const publicName = String(publicNameEl?.value || '').trim();
        const publicNickname = String(publicNicknameEl?.value || '').trim();
        const publicEmail = String(publicEmailEl?.value || '').trim().toLowerCase();
        const publicPhone = String(publicPhoneEl?.value || '').trim();
        const publicUniversity = String(publicUniversityEl?.value || '').trim();
        const publicNoKp = String(publicNoKpEl?.value || '').trim();
        const publicPassword = String(publicPasswordEl?.value || '');
        const publicPasswordConfirm = String(publicPasswordConfirmEl?.value || '');

        if (!publicName) {
          validateField(publicNameEl, false);
          isValid = false;
        }
        if (!validateEmailAddress(publicEmail)) {
          validateField(publicEmailEl, false);
          isValid = false;
        }
        if (!publicPassword || publicPassword.length < 6) {
          validateField(publicPasswordEl, false);
          isValid = false;
        }
        if (!publicPasswordConfirm || publicPasswordConfirm !== publicPassword) {
          validateField(publicPasswordConfirmEl, false);
          isValid = false;
        }

        publicPayload = {
          name: publicName,
          nickname: publicNickname,
          email: publicEmail,
          phone: publicPhone,
          university: publicUniversity,
          nokp: publicNoKp,
          password: publicPassword,
          password_confirm: publicPasswordConfirm
        };
      } else {
        const identifierIsValid = currentAddScope === 'student'
          ? !!selectedIdentifier
          : (!!selectedIdentifier && validateStafID(selectedIdentifier));
        if (!identifierIsValid) {
          const $stafSelect2 = jQuery(auStafSelect).data('select2');
          if ($stafSelect2) {
            const $container = jQuery(auStafSelect).next('.select2-container');
            if ($container.length) {
              validateField($container[0], false);
            }
          } else {
            validateField(auStafSelect, false);
          }
          isValid = false;
        } else {
          const $stafSelect2 = jQuery(auStafSelect).data('select2');
          if ($stafSelect2) {
            const $container = jQuery(auStafSelect).next('.select2-container');
            if ($container.length) {
              $container[0].classList.remove('field-invalid');
            }
          } else {
            auStafSelect.classList.remove('field-invalid');
          }
        }
        
        if ((selectedOption && selectedOption.disabled) || (currentAddScope === 'student' && currentStudentSelection && currentStudentSelection.disabled)) {
          const $stafSelect2 = jQuery(auStafSelect).data('select2');
          if ($stafSelect2) {
            const $container = jQuery(auStafSelect).next('.select2-container');
            if ($container.length) {
              validateField($container[0], false);
            }
          } else {
            validateField(auStafSelect, false);
          }
          isValid = false;
        }
      }
      
      // Validate Group dengan validateGroupId function
      const groupSelect = document.getElementById('au_groupKod');
      if (!groupID || groupID === '' || !validateGroupId(groupID)) {
        validateField(groupSelect, false);
        isValid = false;
      } else {
        groupSelect.classList.remove('field-invalid');
      }
      
      if (!isValid) {
        if (!groupID || groupID === '' || !validateGroupId(groupID)) {
          showAddModalTab('#au-settings-tab');
        } else {
          showAddModalTab('#au-info-tab');
        }
        showAuErr(currentAddScope === 'public'
          ? '<?= h(__('userList_validation_required_public')) ?>'
          : '<?= h(__('userList_validation_required_access')) ?>');
        return; // Stop submission if validation fails
      }
      
      const saveBtn = this;
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> <?= h(__('userList_btn_saving')) ?>';
      
      try {
        const isStudentScope = currentAddScope === 'student';
        const isPublicScope = currentAddScope === 'public';
        const url = isStudentScope
          ? '<?= base_url('ajax/user-add-student.php') ?>'
          : (isPublicScope ? '<?= base_url('ajax/user-add-public.php') ?>' : '<?= base_url('ajax/user-add.php') ?>');
        const requestBody = isStudentScope
          ? {
              scope: currentAddScope,
              matrik: selectedIdentifier || '',
              groupID: parseInt(groupID, 10),
              flag: flag,
              csrf_token: CSRF
            }
          : (isPublicScope
            ? {
                scope: currentAddScope,
                groupID: parseInt(groupID, 10),
                flag: flag,
                csrf_token: CSRF,
                ...publicPayload
              }
            : {
              scope: currentAddScope,
              nopekerja: selectedIdentifier || '',
              idpekerja: idpekerja,
              groupID: parseInt(groupID, 10),
              flag: flag,
              csrf_token: CSRF
            });

        trackEvent('user_add', { scope: currentAddScope, identifier: isPublicScope ? (publicPayload?.email || '') : selectedIdentifier, groupID: parseInt(groupID, 10), flag });
        
        const r = await fetchWithRetry(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(requestBody)
        });
        
        // Read response once
        let responseText = '';
        let j = null;
        
        try {
          responseText = await r.text();
          j = JSON.parse(responseText);
        } catch (e) {
          throw new Error(`<?= h(__('userList_err_invalid_response')) ?> (${r.status}).`);
        }
        
        if (!r.ok) {
          let errorMsg = '<?= h(__('userList_err_add_failed')) ?>';
          if (j && j.message) {
            errorMsg = j.message;
          } else {
            errorMsg = `<?= h(__('userList_http_status_prefix')) ?> ${r.status}: ${r.statusText || '<?= h(__('userList_err_server')) ?>'}`;
          }
          throw new Error(errorMsg);
        }
        
        if (!j || j.error) {
          throw new Error((j && j.message) || '<?= h(__('userList_err_add_failed')) ?>');
        }
        
        trackEvent('user_add_success', { scope: currentAddScope, userID: j.userID, identifier: isPublicScope ? (publicPayload?.email || '') : selectedIdentifier, groupID: parseInt(groupID, 10) });
        
        // Close modal
        const addUserModal = bootstrap.Modal.getInstance(addUserModalEl);
        if (addUserModal) {
          addUserModal.hide();
        }
        
        if (isStudentScope) {
          try {
            sessionStorage.setItem('userListActiveTab', '#tab-student-access');
          } catch (e) { /* ignore */ }
          appendUserRowToTable('userDTStudent', j.row || null);
          setupScopedTableControls('userDTStudent', 'student', {
            addLabel: '<?= h(__('userList_add_student_button')) ?>'
          });
        } else if (isPublicScope) {
          try {
            sessionStorage.setItem('userListActiveTab', '#tab-public-access');
          } catch (e) { /* ignore */ }
          appendUserRowToTable('userDTPublic', j.row || null);
          setupScopedTableControls('userDTPublic', 'public', {
            addLabel: '<?= h(__('userList_add_public_button')) ?>'
          });
        } else {
          await reloadUserTable(j.userID || null);
          await refreshStafDropdown();
        }
        
        // Show success message
        await fireSwal({
          icon: 'success',
          title: '<?= h(__('userList_success_title')) ?>',
          text: (j.message || (isStudentScope ? '<?= h(__('userList_success_add_student')) ?>' : '<?= h(__('userList_success_add')) ?>')),
          confirmButtonText: '<?= h(__('userList_btn_ok')) ?>',
          confirmButtonColor: '#28a745',
          timer: 2000,
          timerProgressBar: true
        });
      } catch (e) {
        const errorMsg = sanitizeError(e);
        trackEvent('user_add_error', { scope: currentAddScope, identifier: currentAddScope === 'public' ? (publicPayload?.email || '') : selectedIdentifier, error: errorMsg });
        showAuErr(errorMsg || '<?= h(__('userList_err_add_failed')) ?>');
      } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      }
    }, 1000));

  });
})();
</script>

</body>
</html>

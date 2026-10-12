<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// controllers/UserListController.php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class UserListController {
  public string $lang = 'ms';
  public array  $profile = [];
  public array  $senaraiUser = [];
  public array  $senaraiStaf = [];

  public int    $page = 1;       // tak guna untuk DataTables client-side
  public int    $perPage = 25;   // tak guna untuk DataTables client-side
  public int    $total = 0;
  public int    $lastPage = 1;
  public ?string $groupFilter = null;
  public string $q = '';

  private PDO $pdo;
  
  // Debug info untuk sync
  public array $syncDebug = [];

  private static function isStaffOptionRecord(array $row): bool {
    $nopekerja = trim((string)($row['nopekerja'] ?? ''));
    $idpekerja = trim((string)($row['idpekerja'] ?? ''));
    $jawatan   = trim((string)($row['jawatan'] ?? ''));
    $jabatan   = trim((string)($row['jabatan'] ?? ''));

    if ($nopekerja === '') {
      return false;
    }

    return $idpekerja !== '' || $jawatan !== '' || $jabatan !== '';
  }

  public function __construct() {
    $this->lang = $_SESSION['lang'] ?? 'ms';
    $this->pdo  = Database::getInstance('mysql')->getConnection();

    $userModel  = new User($this->pdo);
    $f_loginID  = $_SESSION['f_loginID'] ?? null;
    $f_stafID   = $_SESSION['f_stafID'] ?? null;
    if ($f_loginID) {
      $this->profile = $userModel->getProfileByLoginID((string)$f_loginID) ?: [];
    }
    if (!$this->profile && $f_stafID) {
      $this->profile = $userModel->getProfile((string)$f_stafID) ?: [];
    }

    $themeSetting = json_decode($this->profile['f_themeSetting'] ?? '{}', true) ?: [];
    $_SESSION['theme.menu']   = $themeSetting['sidebarColor'] ?? $_SESSION['theme.menu'] ?? 'light';
    $_SESSION['theme.topbar'] = $themeSetting['topbarColor']  ?? $_SESSION['theme.topbar'] ?? 'light';
    $_SESSION['theme.layout'] = $themeSetting['layoutMode']   ?? $_SESSION['theme.layout'] ?? 'light';

    // ❌ Abaikan filter kumpulan – kita nak semua kumpulan keluar
    $this->groupFilter = null;

    // Carian teks opsyenal (nama/stafID/nopekerja)
    $this->q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    // NOTE: Removed automatic sync on constructor to avoid running expensive
    // Sybase -> MySQL sync on every page load. Sync should be triggered
    // explicitly via the manual AJAX endpoint which calls
    // `syncUsersFromSybaseManual()` to record audit events only when a
    // user requests it.

    $this->loadUsers();
    // Load staff list for add-user modal as a safe fallback (may be empty if Sybase not available)
    try {
      $this->loadStaffForModal();
    } catch (Throwable $e) {
      // Non-fatal: keep page working even if staff list cannot be loaded
      error_log('[UserListController] loadStaffForModal failed: ' . $e->getMessage());
    }
  }

  /**
   * Load staff list from Sybase for populating add-user modal dropdown.
   * This is a best-effort fallback to populate the page when the AJAX
   * endpoint cannot be reached by client-side code.
   */
  private function loadStaffForModal(): void {
    try {
      $pdo = Database::pdoSybaseStaff();
      $sql = "
        SELECT DISTINCT
          LTRIM(RTRIM(s.nopekerja))     AS nopekerja,
          LTRIM(RTRIM(s.idpekerja))     AS idpekerja,
          LTRIM(RTRIM(s.gelar_nama))    AS nama,
          LTRIM(RTRIM(s.jawatansemasa)) AS jawatan,
          LTRIM(RTRIM(s.jabatansemasa)) AS jabatan
        FROM v630staf_service_skim_all s
        WHERE CONVERT(INT, s.kodstatus) = 1
          AND s.nopekerja IS NOT NULL
          AND LTRIM(RTRIM(s.nopekerja)) <> ''
        ORDER BY s.gelar_nama ASC
      ";
      $stmt = $pdo->query($sql);
      $rows = array_values(array_filter(
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        static fn($row) => is_array($row) && self::isStaffOptionRecord($row)
      ));
      $this->senaraiStaf = $rows;
    } catch (Throwable $e) {
      // Don't throw further; leave senaraiStaf empty
      $this->senaraiStaf = [];
      error_log('[UserListController] loadStaffForModal DB error: ' . $e->getMessage());
    }
  }

  private function loadUsers(): void {
    $userModelForSchema = new User($this->pdo);
    $hasNickname = $userModelForSchema->authTableHasColumn('f_nickname');
    $hasAutoProvisioned = $userModelForSchema->authTableHasColumn('f_isAutoProvisioned');
    $hasIdentitySource = $userModelForSchema->authTableHasColumn('f_identitySource');

    $where  = [
      "COALESCE(u.f_statusID,0) <> 9",
      "TRIM(COALESCE(u.f_categoryUser, '')) = 'STAF'"
    ];
    $params = [];

    // ❌ Tiada tapisan kumpulan
    if ($this->q !== '') {
      $where[] = "(u.f_nama LIKE :q OR u.f_stafID LIKE :q OR u.f_nopekerja LIKE :q)";
      $params[':q'] = "%{$this->q}%";
    }
    $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

    // Kira total penuh (tanpa LIMIT)
    $sqlCount = "
      SELECT COUNT(*)
      FROM tbl_m_user u
      LEFT JOIN tbl_m_group g
        ON g.f_groupID = u.f_groupID
      $whereSql
    ";
    $stmt = $this->pdo->prepare($sqlCount);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();
    $this->total = (int)$stmt->fetchColumn();
    $this->lastPage = 1;

    // Ambil SEMUA rekod (biar DataTables urus paging client-side)
    $selectFields = [
      'u.f_userID',
      'u.f_loginID',
      'u.f_stafID',
      'u.f_nopekerja',
      'u.f_categoryUser',
      'u.f_nama',
      'u.f_namajabatan',
      'u.f_jawatan',
      'u.f_status',
      'u.f_flag',
      $hasNickname ? 'u.f_nickname' : "'' AS f_nickname",
      $hasAutoProvisioned ? 'COALESCE(u.f_isAutoProvisioned, 0) AS f_isAutoProvisioned' : '0 AS f_isAutoProvisioned',
      $hasIdentitySource ? "TRIM(COALESCE(u.f_identitySource, '')) AS f_identitySource" : "'' AS f_identitySource",
      'u.f_groupID',
      'TRIM(u.f_groupKod) AS f_groupKod',
      "COALESCE(NULLIF(TRIM(g.f_groupName), ''), TRIM(u.f_groupKod)) AS f_groupName",
    ];

    $sql = "
      SELECT
        " . implode(",\n        ", $selectFields) . "
      FROM tbl_m_user u
      LEFT JOIN tbl_m_group g
        ON g.f_groupID = u.f_groupID
      $whereSql
      ORDER BY u.f_nama ASC
    ";
    $stmt = $this->pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();

    $this->senaraiUser = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Attach additional roles (tbl_ref_access) for UI badges
    if (!empty($this->senaraiUser)) {
      $userIds = [];
      $stafIds = [];
      foreach ($this->senaraiUser as $u) {
        $uid = (int)($u['f_userID'] ?? 0);
        if ($uid > 0) {
          $userIds[] = $uid;
          continue;
        }
        $sid = trim((string)($u['f_stafID'] ?? ''));
        if ($sid !== '') $stafIds[] = $sid;
      }
      $userIds = array_values(array_unique($userIds));
      $stafIds = array_values(array_unique($stafIds));
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
          ORDER BY g.f_groupName ASC
        ";
        $stmtByUser = $this->pdo->prepare($sqlExtraByUser);
        $stmtByUser->execute($userIds);
        $rowsByUser = $stmtByUser->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rowsByUser as $r) {
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
          ORDER BY g.f_groupName ASC
        ";
        $stmtX = $this->pdo->prepare($sqlExtra);
        $stmtX->execute($stafIds);
        $rows = $stmtX->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
          $sid = (string)($r['f_stafID'] ?? '');
          $rname = (string)($r['f_groupName'] ?? '');
          if ($sid === '' || $rname === '') continue;
          $mapByStafId[$sid][] = $rname;
        }
      }
      foreach ($this->senaraiUser as &$u) {
        $uid = (int)($u['f_userID'] ?? 0);
        $sid = trim((string)($u['f_stafID'] ?? ''));
        $extra = $uid > 0 ? ($mapByUserId[$uid] ?? []) : ($mapByStafId[$sid] ?? []);
        $u['extra_roles'] = $extra;
        $u['extra_roles_count'] = count($extra);
      }
      unset($u);
    }
  }

  /**
   * Sync data staf dari Sybase (v630staf_service_skim_all) ke MySQL (tbl_m_user)
   * Hanya UPDATE record yang sudah wujud, tidak INSERT
   */
  private function syncUsersFromSybase(): void {
    $this->syncDebug = ['started' => date('Y-m-d H:i:s')];
    
    try {
      error_log("[UserListController] Starting sync from Sybase...");
      $this->syncDebug['step'] = 'Connecting to Sybase...';
      
      // Connect ke Sybase aktif
      $pdoSybase = Database::pdoSybaseStaff();
      error_log("[UserListController] Sybase connection successful");
      $this->syncDebug['step'] = 'Sybase connected';
      
      // Test query untuk verify connection works
      try {
        $testStmt = $pdoSybase->query("SELECT 1 as test");
        $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
        error_log("[UserListController] Sybase test query successful: " . json_encode($testResult));
      } catch (Throwable $e) {
        error_log("[UserListController] Sybase test query failed: " . $e->getMessage());
        throw $e;
      }
      
      // Query semua staf supaya perubahan status aktif/tidak aktif
      // turut dikemas kini ke tbl_m_user semasa sync.
      $sql = "
        SELECT 
          nopekerja,
          idpekerja,
          gelar_nama,
          nama,
          nokp,
          email,
          handphone,
          kdjwtsemasa,
          jawatansemasa,
          kdjenis,
          jenis,
          kdjbtnsemasa,
          jabatansemasa,
          kumpjwt,
          kodstatus,
          status
        FROM v630staf_service_skim_all
      ";
      
      error_log("[UserListController] Executing query: " . substr($sql, 0, 100) . "...");
      
      $stmt = $pdoSybase->prepare($sql);
      $stmt->execute();
      $sybaseUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      $sybaseCount = count($sybaseUsers);
      error_log("[UserListController] Fetched {$sybaseCount} staff records from Sybase");
      $this->syncDebug['sybase_count'] = $sybaseCount;
      
      // Log sample data untuk debugging
      if (!empty($sybaseUsers)) {
        $sample = $sybaseUsers[0];
        error_log("[UserListController] Sample data: nopekerja=" . ($sample['nopekerja'] ?? 'NULL') . ", nama=" . ($sample['gelar_nama'] ?? 'NULL'));
        $this->syncDebug['sample_nopekerja'] = $sample['nopekerja'] ?? 'NULL';
      }
      
      if (empty($sybaseUsers)) {
        error_log("[UserListController] No data from Sybase to sync");
        $this->syncDebug['error'] = 'No data from Sybase';
        return; // Tiada data untuk sync
      }
      
      // Prepare UPDATE statement untuk MySQL
      // Ambil staf ID yang login untuk f_updateby
      $loggedInStafID = $_SESSION['f_stafID'] ?? null;
      $remarks = 'Sync from Sybase (v630staf_service_skim_all) on page load';
      
      $updateSql = "
        UPDATE tbl_m_user SET
          f_nopekerja = :idpekerja,
          f_categoryUser = 'STAF',
          f_nama = :gelar_nama,
          f_nickname = :nama,
          f_nokp = :nokp,
          f_email = :email,
          f_handphone = :handphone,
          f_jawatanKod = :kdjwtsemasa,
          f_jawatan = :jawatansemasa,
          f_jenisID = :kdjenis,
          f_jenis = :jenis,
          f_jabatanKod = :kdjbtnsemasa,
          f_namajabatan = :jabatansemasa,
          f_kumpjawatan = :kumpjwt,
          f_statusID = :kodstatus_status,
          f_status = :status,
          f_flag = CASE WHEN COALESCE(:kodstatus_flag, 0) = 1 THEN f_flag ELSE 0 END,
          f_updatedt = NOW(),
          f_updateby = :updateby,
          f_remarks = :remarks
        WHERE f_stafID = :nopekerja
      ";
      
      // Helper: normalize stafID untuk matching (remove dashes, trim)
      $normalizeStafID = function($id) {
        return str_replace('-', '', trim((string)$id));
      };
      
      // Ambil semua f_stafID yang wujud dalam MySQL (optimize: sekali query sahaja)
      // Store both original and normalized for matching
      $existingStafIDs = [];
      $existingStafIDsNormalized = [];
      $checkAllSql = "SELECT f_stafID FROM tbl_m_user";
      $checkAllStmt = $this->pdo->query($checkAllSql);
      while ($row = $checkAllStmt->fetch(PDO::FETCH_ASSOC)) {
        $original = trim((string)($row['f_stafID'] ?? ''));
        $normalized = $normalizeStafID($original);
        $existingStafIDs[$original] = $original; // Store original for UPDATE WHERE clause
        $existingStafIDsNormalized[$normalized] = $original; // Store normalized for matching
      }
      
      $mysqlCount = count($existingStafIDs);
      error_log("[UserListController] Found {$mysqlCount} existing users in MySQL");
      $this->syncDebug['mysql_count'] = $mysqlCount;
      
      $updateStmt = $this->pdo->prepare($updateSql);
      $updatedCount = 0;
      $skippedCount = 0;
      $errorCount = 0;
      
      // Update setiap record yang match
      foreach ($sybaseUsers as $sybaseUser) {
        $nopekerja = trim((string)($sybaseUser['nopekerja'] ?? ''));
        
        if (empty($nopekerja)) {
          $skippedCount++;
          continue; // Skip jika nopekerja kosong
        }
        
        // Normalize untuk matching (remove dashes)
        $nopekerjaNormalized = $normalizeStafID($nopekerja);
        
        // Check jika record wujud dalam MySQL (guna normalized lookup)
        if (!isset($existingStafIDsNormalized[$nopekerjaNormalized])) {
          $skippedCount++;
          continue; // Skip jika tidak wujud (tidak INSERT)
        }
        
        // Get original f_stafID from MySQL untuk UPDATE WHERE clause
        $mysqlStafID = $existingStafIDsNormalized[$nopekerjaNormalized];
        
        // Update record (gunakan original MySQL f_stafID untuk WHERE clause)
        try {
          $result = $updateStmt->execute([
            ':nopekerja' => $mysqlStafID, // Use original MySQL f_stafID for WHERE clause
            ':idpekerja' => $sybaseUser['idpekerja'] ?? null, // idpekerja -> f_nopekerja
            ':gelar_nama' => $sybaseUser['gelar_nama'] ?? null,
            ':nama' => $sybaseUser['nama'] ?? null,
            ':nokp' => $sybaseUser['nokp'] ?? null,
            ':email' => $sybaseUser['email'] ?? null,
            ':handphone' => $sybaseUser['handphone'] ?? null,
            ':kdjwtsemasa' => $sybaseUser['kdjwtsemasa'] ?? null,
            ':jawatansemasa' => $sybaseUser['jawatansemasa'] ?? null,
            ':kdjenis' => !empty($sybaseUser['kdjenis']) ? (int)$sybaseUser['kdjenis'] : null,
            ':jenis' => $sybaseUser['jenis'] ?? null,
            ':kdjbtnsemasa' => $sybaseUser['kdjbtnsemasa'] ?? null,
            ':jabatansemasa' => $sybaseUser['jabatansemasa'] ?? null,
            ':kumpjwt' => $sybaseUser['kumpjwt'] ?? null,
            ':kodstatus_status' => !empty($sybaseUser['kodstatus']) ? (int)$sybaseUser['kodstatus'] : null,
            ':kodstatus_flag' => !empty($sybaseUser['kodstatus']) ? (int)$sybaseUser['kodstatus'] : null,
            ':status' => $sybaseUser['status'] ?? null,
            ':updateby' => $loggedInStafID,
            ':remarks' => $remarks,
          ]);
          
          if ($result) {
            $updatedCount++;
          } else {
            $errorCount++;
            error_log("[UserListController] Update failed for nopekerja: {$nopekerja}");
          }
        } catch (PDOException $e) {
          $errorCount++;
          error_log("[UserListController] Update error for nopekerja {$nopekerja}: " . $e->getMessage());
        }
      }
      
      // Log sync result dengan detail
      error_log("[UserListController] Sync completed - Updated: {$updatedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}");
      
      $this->syncDebug['completed'] = date('Y-m-d H:i:s');
      $this->syncDebug['updated'] = $updatedCount;
      $this->syncDebug['skipped'] = $skippedCount;
      $this->syncDebug['errors'] = $errorCount;
      $this->syncDebug['status'] = 'success';
      
      // ✅ Audit: Log user sync operation (summary for bulk operation)
      try {
        if (function_exists('audit_event')) {
          $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
          $sessionId = session_id() ?: null;
          
          // Derive numeric user_id for audit (prefer f_userID then parse staff no; DB fallback)
          $userId = null;
          if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
            $userId = (int)$_SESSION['user']['f_userID'];
          } elseif (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
            $userId = (int)$_SESSION['f_userID'];
          } else {
            $cand = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
            if ($cand) {
              if (is_numeric($cand)) $userId = (int)$cand;
              elseif (preg_match('/^(\d+)/', (string)$cand, $m)) $userId = (int)$m[1];
            }
            if ($userId === null && !empty($_SESSION['f_stafID'])) {
              try {
                $up = (new User($this->pdo))->getProfile($_SESSION['f_stafID']);
                if (!empty($up['f_nopekerja'])) {
                  $c = $up['f_nopekerja'];
                  if (is_numeric($c)) $userId = (int)$c;
                  elseif (preg_match('/^(\d+)/', (string)$c, $m2)) $userId = (int)$m2[1];
                }
              } catch (Throwable $e) {
                error_log('[UserListController] user_id derivation DB lookup failed: ' . $e->getMessage());
              }
            }
          }
          
          // Format actor_label
          $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
          $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
          $actorLabel = null;
          if (function_exists('audit_format_actor_label')) {
            $actorLabel = audit_format_actor_label($nama, $nostaf);
          } else {
            $actorLabel = $nama;
          }
          
          // Format message
          $message = audit_format_message('User sync from Sybase completed (auto)', $actorLabel);
          
          audit_event([
            'event_type'  => 'UPDATE',
            'severity'    => 'INFO',
            'outcome'     => ($errorCount > 0) ? 'PARTIAL' : 'SUCCESS',
            'target_type' => 'user_sync',
            'target_id'   => 'bulk_sync',
            'target_label' => 'User Sync (Auto)',
            'message'     => $message,
            'request_id'  => $requestId,
            'session_id'  => $sessionId,
            'user_id'     => $userId,
            'actor_label' => $actorLabel,
            'meta'        => [
              'sync_type' => 'auto',
              'source' => 'v630staf_service_skim_all',
              'updated_count' => $updatedCount,
              'skipped_count' => $skippedCount,
              'error_count' => $errorCount,
              'total_from_sybase' => $sybaseCount,
              'total_in_mysql' => $mysqlCount
            ]
          ]);
        }
      } catch (\Throwable $auditError) {
        error_log('[UserListController::syncUsersFromSybase] Audit error: ' . $auditError->getMessage());
        // Don't block sync if audit fails
      }
      
    } catch (Throwable $e) {
      // Graceful error handling: jika Sybase tidak available, skip sahaja
      // Jangan block page load jika sync gagal
      $errorMsg = $e->getMessage();
      error_log("[UserListController] Sync failed: " . $errorMsg);
      error_log("[UserListController] Stack trace: " . $e->getTraceAsString());
      
      $this->syncDebug['error'] = $errorMsg;
      $this->syncDebug['status'] = 'failed';
      $this->syncDebug['failed_at'] = date('Y-m-d H:i:s');
      
      // Continue execution - page tetap boleh load
    }
  }

  /**
   * Manual sync method untuk AJAX call
   * Returns array with success status and details
   */
  public function syncUsersFromSybaseManual(): array {
    $this->syncDebug = ['started' => date('Y-m-d H:i:s')];
    
    try {
      error_log("[UserListController] Starting manual sync from Sybase...");
      $this->syncDebug['step'] = 'Connecting to Sybase...';
      
      // Connect ke Sybase aktif
      $pdoSybase = Database::pdoSybaseStaff();
      error_log("[UserListController] Sybase connection successful");
      $this->syncDebug['step'] = 'Sybase connected';
      
      // Query semua staf supaya perubahan status aktif/tidak aktif
      // turut dikemas kini ke tbl_m_user semasa sync manual.
      $sql = "
        SELECT 
          nopekerja,
          idpekerja,
          gelar_nama,
          nama,
          nokp,
          email,
          handphone,
          kdjwtsemasa,
          jawatansemasa,
          kdjenis,
          jenis,
          kdjbtnsemasa,
          jabatansemasa,
          kumpjwt,
          kodstatus,
          status
        FROM v630staf_service_skim_all
      ";
      
      $stmt = $pdoSybase->prepare($sql);
      $stmt->execute();
      $sybaseUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      $sybaseCount = count($sybaseUsers);
      error_log("[UserListController] Fetched {$sybaseCount} staff records from Sybase");
      $this->syncDebug['sybase_count'] = $sybaseCount;
      
      if (empty($sybaseUsers)) {
        return [
          'success' => true,
          'message' => 'Tiada data dari Sybase untuk disync.',
          'updated' => 0,
          'skipped' => 0,
          'errors' => 0,
          'total' => 0
        ];
      }
      
      // Prepare UPDATE statement untuk MySQL
      $loggedInStafID = $_SESSION['f_stafID'] ?? null;
      $remarks = 'Manual sync from Sybase (v630staf_service_skim_all)';
      
      $updateSql = "
        UPDATE tbl_m_user SET
          f_nopekerja = :idpekerja,
          f_categoryUser = 'STAF',
          f_nama = :gelar_nama,
          f_nickname = :nama,
          f_nokp = :nokp,
          f_email = :email,
          f_handphone = :handphone,
          f_jawatanKod = :kdjwtsemasa,
          f_jawatan = :jawatansemasa,
          f_jenisID = :kdjenis,
          f_jenis = :jenis,
          f_jabatanKod = :kdjbtnsemasa,
          f_namajabatan = :jabatansemasa,
          f_kumpjawatan = :kumpjwt,
          f_statusID = :kodstatus_status,
          f_status = :status,
          f_flag = CASE WHEN COALESCE(:kodstatus_flag, 0) = 1 THEN f_flag ELSE 0 END,
          f_updatedt = NOW(),
          f_updateby = :updateby,
          f_remarks = :remarks
        WHERE f_stafID = :nopekerja
      ";
      
      // Helper: normalize stafID untuk matching
      $normalizeStafID = function($id) {
        return str_replace('-', '', trim((string)$id));
      };
      
      // Ambil semua f_stafID yang wujud dalam MySQL
      $existingStafIDs = [];
      $existingStafIDsNormalized = [];
      $checkAllSql = "SELECT f_stafID FROM tbl_m_user";
      $checkAllStmt = $this->pdo->query($checkAllSql);
      while ($row = $checkAllStmt->fetch(PDO::FETCH_ASSOC)) {
        $original = trim((string)($row['f_stafID'] ?? ''));
        $normalized = $normalizeStafID($original);
        $existingStafIDs[$original] = $original;
        $existingStafIDsNormalized[$normalized] = $original;
      }
      
      $updateStmt = $this->pdo->prepare($updateSql);
      $updatedCount = 0;
      $skippedCount = 0;
      $errorCount = 0;
      
      // Update setiap record yang match
      foreach ($sybaseUsers as $sybaseUser) {
        $nopekerja = trim((string)($sybaseUser['nopekerja'] ?? ''));
        
        if (empty($nopekerja)) {
          $skippedCount++;
          continue;
        }
        
        $nopekerjaNormalized = $normalizeStafID($nopekerja);
        
        if (!isset($existingStafIDsNormalized[$nopekerjaNormalized])) {
          $skippedCount++;
          continue;
        }
        
        $mysqlStafID = $existingStafIDsNormalized[$nopekerjaNormalized];
        
        try {
          $result = $updateStmt->execute([
            ':nopekerja' => $mysqlStafID,
            ':idpekerja' => $sybaseUser['idpekerja'] ?? null,
            ':gelar_nama' => $sybaseUser['gelar_nama'] ?? null,
            ':nama' => $sybaseUser['nama'] ?? null,
            ':nokp' => $sybaseUser['nokp'] ?? null,
            ':email' => $sybaseUser['email'] ?? null,
            ':handphone' => $sybaseUser['handphone'] ?? null,
            ':kdjwtsemasa' => $sybaseUser['kdjwtsemasa'] ?? null,
            ':jawatansemasa' => $sybaseUser['jawatansemasa'] ?? null,
            ':kdjenis' => !empty($sybaseUser['kdjenis']) ? (int)$sybaseUser['kdjenis'] : null,
            ':jenis' => $sybaseUser['jenis'] ?? null,
            ':kdjbtnsemasa' => $sybaseUser['kdjbtnsemasa'] ?? null,
            ':jabatansemasa' => $sybaseUser['jabatansemasa'] ?? null,
            ':kumpjwt' => $sybaseUser['kumpjwt'] ?? null,
            ':kodstatus_status' => !empty($sybaseUser['kodstatus']) ? (int)$sybaseUser['kodstatus'] : null,
            ':kodstatus_flag' => !empty($sybaseUser['kodstatus']) ? (int)$sybaseUser['kodstatus'] : null,
            ':status' => $sybaseUser['status'] ?? null,
            ':updateby' => $loggedInStafID,
            ':remarks' => $remarks,
          ]);
          
          if ($result) {
            $updatedCount++;
          } else {
            $errorCount++;
          }
        } catch (PDOException $e) {
          $errorCount++;
          error_log("[UserListController] Update error for nopekerja {$nopekerja}: " . $e->getMessage());
        }
      }
      
      $this->syncDebug['completed'] = date('Y-m-d H:i:s');
      $this->syncDebug['updated'] = $updatedCount;
      $this->syncDebug['skipped'] = $skippedCount;
      $this->syncDebug['errors'] = $errorCount;
      $this->syncDebug['status'] = 'success';
      
      // ✅ Audit: Log user sync operation (summary for bulk operation)
      try {
        if (function_exists('audit_event')) {
          $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
          $sessionId = session_id() ?: null;
          
          // Derive numeric user_id for audit (prefer f_userID then parse staff no; DB fallback)
          $userId = null;
          if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
            $userId = (int)$_SESSION['user']['f_userID'];
          } elseif (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
            $userId = (int)$_SESSION['f_userID'];
          } else {
            $cand = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
            if ($cand) {
              if (is_numeric($cand)) $userId = (int)$cand;
              elseif (preg_match('/^(\d+)/', (string)$cand, $m)) $userId = (int)$m[1];
            }
            if ($userId === null && !empty($_SESSION['f_stafID'])) {
              try {
                $up = (new User($this->pdo))->getProfile($_SESSION['f_stafID']);
                if (!empty($up['f_nopekerja'])) {
                  $c = $up['f_nopekerja'];
                  if (is_numeric($c)) $userId = (int)$c;
                  elseif (preg_match('/^(\d+)/', (string)$c, $m2)) $userId = (int)$m2[1];
                }
              } catch (Throwable $e) {
                error_log('[UserListController] user_id derivation DB lookup failed: ' . $e->getMessage());
              }
            }
          }
          
          // Format actor_label
          $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
          $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
          $actorLabel = null;
          if (function_exists('audit_format_actor_label')) {
            $actorLabel = audit_format_actor_label($nama, $nostaf);
          } else {
            $actorLabel = $nama;
          }
          
          // Get MySQL count
          $mysqlCount = count($existingStafIDs);
          
          // Format message
          $message = audit_format_message('User sync from Sybase completed (manual)', $actorLabel);
          
          audit_event([
            'event_type'  => 'UPDATE',
            'severity'    => 'INFO',
            'outcome'     => ($errorCount > 0) ? 'PARTIAL' : 'SUCCESS',
            'target_type' => 'user_sync',
            'target_id'   => 'bulk_sync',
            'target_label' => 'User Sync (Manual)',
            'message'     => $message,
            'request_id'  => $requestId,
            'session_id'  => $sessionId,
            'user_id'     => $userId,
            'actor_label' => $actorLabel,
            'meta'        => [
              'sync_type' => 'manual',
              'source' => 'v630staf_service_skim_all',
              'updated_count' => $updatedCount,
              'skipped_count' => $skippedCount,
              'error_count' => $errorCount,
              'total_from_sybase' => $sybaseCount,
              'total_in_mysql' => $mysqlCount
            ]
          ]);
        }
      } catch (\Throwable $auditError) {
        error_log('[UserListController::syncUsersFromSybaseManual] Audit error: ' . $auditError->getMessage());
        // Don't block sync if audit fails
      }
      
      return [
        'success' => true,
        'message' => sprintf(
          (string)(__('userList_sync_result_message') ?: 'Sync berjaya. %d rekod dikemas kini, %d rekod dilangkau, %d ralat.'),
          $updatedCount,
          $skippedCount,
          $errorCount
        ),
        'updated' => $updatedCount,
        'skipped' => $skippedCount,
        'errors' => $errorCount,
        'total' => $sybaseCount
      ];
      
    } catch (Throwable $e) {
      $errorMsg = $e->getMessage();
      error_log("[UserListController] Manual sync failed: " . $errorMsg);
      
      return [
        'success' => false,
        'message' => 'Gagal sync data dari Sybase: ' . $errorMsg,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'total' => 0
      ];
    }
  }
}

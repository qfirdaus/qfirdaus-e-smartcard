<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-add.php
// Staff-only add flow from Sybase data into tbl_m_user
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    logAjaxUnexpectedOutput('user-add:init.php', $initOutput);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonErrorResponse((string)__('userList_ajax_method_not_allowed'), 405);
    }

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/User.php';

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);
    if (!userListCanAddUsers($pdo)) {
        jsonErrorResponse((string)__('userList_err_no_permission'), 403);
    }
    $userSchema = new User($pdo);

    // Rate limiting: max 20 requests per 60 seconds
    if (!checkRateLimit('user_add', 20, 60)) {
        jsonErrorResponse((string)__('userList_ajax_rate_limited'), 429);
    }

    $readPayload = static function (): array {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            jsonErrorResponse((string)__('userList_ajax_invalid_data'), 400);
        }

        if (!isValidCsrfToken((string)($data['csrf_token'] ?? ''))) {
            jsonErrorResponse((string)__('userGroup_csrf_invalid'), 400);
        }

        $nopekerja = trim((string)($data['nopekerja'] ?? ''));
        $idpekerja = trim((string)($data['idpekerja'] ?? ''));
        $scope = strtolower(trim((string)($data['scope'] ?? 'staff')));
        $groupID = (int)($data['groupID'] ?? 0);
        $flag = isset($data['flag']) ? (int)$data['flag'] : 1;

        if ($scope !== 'staff' && $scope !== 'staf') {
            jsonErrorResponse((string)__('userList_ajax_staff_scope_only'), 400);
        }

        if ($nopekerja === '') {
            jsonErrorResponse((string)__('userList_ajax_employee_no_required'), 400);
        }

        if (!in_array($flag, [0, 1], true)) {
            $flag = 1;
        }

        return [
            'data' => $data,
            'scope' => $scope,
            'nopekerja' => $nopekerja,
            'idpekerja' => $idpekerja,
            'groupID' => $groupID,
            'flag' => $flag,
        ];
    };

    $resolveGroup = static function (PDO $pdo, int $groupID): array {
        if ($groupID <= 0) {
            jsonErrorResponse((string)__('userList_ajax_invalid_group'), 400);
        }

        $groupCheckSql = "SELECT f_groupID, f_groupKod, f_categoryUser FROM tbl_m_group WHERE f_groupID = :groupID LIMIT 1";
        $groupCheckStmt = $pdo->prepare($groupCheckSql);
        $groupCheckStmt->execute([':groupID' => $groupID]);
        $groupRow = $groupCheckStmt->fetch(PDO::FETCH_ASSOC);

        if (!$groupRow) {
            jsonErrorResponse((string)__('userList_ajax_invalid_group'), 400);
        }

        $groupCategory = strtoupper(trim((string)($groupRow['f_categoryUser'] ?? '')));
        if ($groupCategory !== 'STAF') {
            jsonErrorResponse((string)__('userList_ajax_invalid_staff_group'), 400);
        }

        return [
            'groupID' => (int)($groupRow['f_groupID'] ?? 0),
            'groupKod' => (string)($groupRow['f_groupKod'] ?? ''),
        ];
    };

    $ensureUserNotExists = static function (PDO $pdo, string $nopekerja): void {
        $checkSql = "SELECT f_userID FROM tbl_m_user WHERE f_stafID = :staff_identifier OR TRIM(COALESCE(f_loginID, '')) = :login_identifier LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([
            ':staff_identifier' => $nopekerja,
            ':login_identifier' => $nopekerja,
        ]);
        if ($checkStmt->fetch()) {
            jsonErrorResponse((string)__('userList_ajax_employee_exists'), 409);
        }
    };

    $fetchSybaseUser = static function (string $nopekerja): array {
        $pdoSybase = Database::pdoSybaseStaff();
        $sybaseSql = "
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
            WHERE nopekerja = :nopekerja
              AND CONVERT(INT, kodstatus) = 1
        ";
        $sybaseStmt = $pdoSybase->prepare($sybaseSql);
        $sybaseStmt->execute([':nopekerja' => $nopekerja]);
        $sybaseUser = $sybaseStmt->fetch(PDO::FETCH_ASSOC);
        error_log('[user-add] Sybase query result: ' . ($sybaseUser ? 'found' : 'not found') . ' for nopekerja: ' . $nopekerja);

        if (!$sybaseUser) {
            jsonErrorResponse((string)__('userList_ajax_staff_source_not_found'), 404);
        }

        return $sybaseUser;
    };

    $deriveAuditUserId = static function (): ?int {
        if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
            return (int)$_SESSION['user']['f_userID'];
        }
        if (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
            return (int)$_SESSION['f_userID'];
        }

        $candidate = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
        if ($candidate) {
            if (is_numeric($candidate)) {
                return (int)$candidate;
            }
            if (preg_match('/^(\d+)/', (string)$candidate, $m)) {
                return (int)$m[1];
            }
        }

        if (!empty($_SESSION['f_stafID'])) {
            try {
                $lookupPdo = Database::getInstance('mysql')->getConnection();
                require_once __DIR__ . '/../classes/User.php';
                $lookupUserModel = new User($lookupPdo);
                $userProfile = $lookupUserModel->getProfile($_SESSION['f_stafID']);
                if (!empty($userProfile['f_nopekerja'])) {
                    $cand = $userProfile['f_nopekerja'];
                    if (is_numeric($cand)) {
                        return (int)$cand;
                    }
                    if (preg_match('/^(\d+)/', (string)$cand, $m2)) {
                        return (int)$m2[1];
                    }
                }
            } catch (Throwable $e) {
                error_log('[user-add] user_id derivation DB lookup failed: ' . $e->getMessage());
            }
        }

        return null;
    };

    $buildAddAuditData = static function (string $nopekerja, array $sybaseUser, int $groupID, string $groupKod, int $flag, int $newUserId) use ($deriveAuditUserId): array {
        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
        $sessionId = session_id() ?: null;
        $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
        $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
        $formattedActorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label($nama, $nostaf)
            : $nama;
        $message = audit_format_message('User created from Sybase data', $formattedActorLabel);
        $userId = $deriveAuditUserId();
        $targetName = trim((string)($sybaseUser['gelar_nama'] ?? $sybaseUser['nama'] ?? $nopekerja));
        $statusSuffix = '';
        if (!empty($sybaseUser['status'])) {
            $st = trim((string)$sybaseUser['status']);
            if ($st !== '' && stripos($targetName, $st) === false) {
                $statusSuffix = ' (' . $st . ')';
            }
        }

        error_log("[user-add] Audit prep: request_id={$requestId}, session_id={$sessionId}, user_id=" . ($userId ?? 'null') . ", actor={$formattedActorLabel}");

        return [
            'event_type' => 'CREATE',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'user',
            'target_id' => (string)$nopekerja,
            'target_label' => 'User: ' . $targetName . $statusSuffix,
            'message' => $message,
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'actor_label' => $formattedActorLabel,
            'meta' => [
                'groupID' => $groupID,
                'groupKod' => $groupKod,
                'flag' => $flag,
                'source' => 'user_add_ajax',
                'userID' => $newUserId,
            ],
        ];
    };

    $insertUserFromSybase = static function (
        PDO $pdo,
        string $nopekerja,
        string $idpekerja,
        array $sybaseUser,
        int $groupID,
        string $groupKod,
        int $flag,
        ?string $loggedInStafID,
        User $userSchema
    ): int {
        $nokp = $sybaseUser['nokp'] ?? '';
        $hashedPassword = '';
        if (!empty($nokp)) {
            $hashedPassword = password_hash($nokp, PASSWORD_DEFAULT);
        }
        $columnValueMap = [
            'f_loginID' => $nopekerja,
            'f_stafID' => $nopekerja,
            'f_categoryUser' => 'STAF',
            'f_nopekerja' => $idpekerja ?: ($sybaseUser['idpekerja'] ?? null),
            'f_nama' => $sybaseUser['gelar_nama'] ?? null,
            'f_nickname' => $sybaseUser['nama'] ?? null,
            'f_nokp' => $sybaseUser['nokp'] ?? null,
            'f_password' => $hashedPassword,
            'f_email' => $sybaseUser['email'] ?? null,
            'f_handphone' => $sybaseUser['handphone'] ?? null,
            'f_jawatanKod' => $sybaseUser['kdjwtsemasa'] ?? null,
            'f_jawatan' => $sybaseUser['jawatansemasa'] ?? null,
            'f_jenisID' => !empty($sybaseUser['kdjenis']) ? (int)$sybaseUser['kdjenis'] : null,
            'f_jenis' => $sybaseUser['jenis'] ?? null,
            'f_jabatanKod' => $sybaseUser['kdjbtnsemasa'] ?? null,
            'f_namajabatan' => $sybaseUser['jabatansemasa'] ?? null,
            'f_kumpjawatan' => $sybaseUser['kumpjwt'] ?? null,
            'f_verified_at' => '__SQL_NOW__',
            'f_must_change_password' => 1,
            'f_password_changed_at' => null,
            'f_password_expires_at' => null,
            'f_statusID' => !empty($sybaseUser['kodstatus']) ? (int)$sybaseUser['kodstatus'] : null,
            'f_status' => $sybaseUser['status'] ?? null,
            'f_groupID' => $groupID,
            'f_groupKod' => $groupKod,
            'f_flag' => $flag,
            'f_insertdt' => '__SQL_NOW__',
            'f_updatedt' => '__SQL_NOW__',
            'f_updateby' => $loggedInStafID,
            'f_remarks' => 'Added via Tambah Pengguna form',
        ];

        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($columnValueMap as $column => $value) {
            if (!$userSchema->authTableHasColumn($column)) {
                continue;
            }
            $columns[] = $column;
            if ($value === '__SQL_NOW__') {
                $placeholders[] = 'NOW()';
                continue;
            }
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        $insertSql = "INSERT INTO tbl_m_user (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $insertStmt = $pdo->prepare($insertSql);
        $result = $insertStmt->execute($params);

        if (!$result) {
            throw new Exception('Gagal menyimpan data pengguna.');
        }

        return (int)$pdo->lastInsertId();
    };

    $logUserAddAudit = static function (string $nopekerja, array $sybaseUser, int $groupID, string $groupKod, int $flag, int $newUserId) use ($buildAddAuditData): ?int {
        if (!function_exists('audit_event')) {
            error_log('[user-add] audit_event() function not found');
            return null;
        }

        $auditData = $buildAddAuditData($nopekerja, $sybaseUser, $groupID, $groupKod, $flag, $newUserId);
        $eventId = audit_event($auditData);
        error_log("[user-add] Audit event result: event_id=" . ($eventId ?? 'null') . ", request_id=" . ($auditData['request_id'] ?? 'null') . ", session_id=" . ($auditData['session_id'] ?? 'null') . ", user_id=" . ($auditData['user_id'] ?? 'null'));
        return $eventId ?: null;
    };

    $clearAddCaches = static function (): void {
        if (isset($_SESSION['userlist_cache']['staf_options_list'])) {
            unset($_SESSION['userlist_cache']['staf_options_list']);
        }
    };

    $payload = $readPayload();
    $nopekerja = $payload['nopekerja'];
    $idpekerja = $payload['idpekerja'];
    $groupID = $payload['groupID'];
    $flag = $payload['flag'];

    $group = $resolveGroup($pdo, $groupID);
    userListEnsureAssignableGroup($pdo, $groupID);
    $groupID = $group['groupID'];
    $groupKod = $group['groupKod'];

    $ensureUserNotExists($pdo, $nopekerja);
    $sybaseUser = $fetchSybaseUser($nopekerja);

    // Get logged in user for audit
    $loggedInStafID = $_SESSION['f_stafID'] ?? null;
    $newUserId = $insertUserFromSybase($pdo, $nopekerja, $idpekerja, $sybaseUser, $groupID, $groupKod, $flag, $loggedInStafID, $userSchema);

    // Audit: Log user creation
    try {
        $logUserAddAudit($nopekerja, $sybaseUser, $groupID, $groupKod, $flag, $newUserId);
    } catch (\Throwable $e) {
        // Don't block user creation if audit fails
        error_log('[user-add] Audit logging failed: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    }

    $clearAddCaches();
    
    jsonSuccessResponse([
        'message' => (string)__('userList_success_add'),
        'userID' => $newUserId
    ]);

} catch (PDOException $e) {
    error_log('[user-add] PDO Error: ' . $e->getMessage());
    
    // Check for duplicate entry
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
        jsonErrorResponse((string)__('userList_ajax_employee_exists'), 409);
    } else {
        jsonErrorResponse((string)__('userList_ajax_database_error'), 500);
    }
} catch (Throwable $e) {
    error_log('[user-add] Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_add_system_error'), 500);
}

<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-add-student.php
// Student-only add flow from v210 data into tbl_m_user
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../includes/functions-db.php';
    logAjaxUnexpectedOutput('user-add-student:init.php', $initOutput);

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

    if (!is_student_mode_enabled()) {
        jsonErrorResponse((string)__('studentSearch_mode_disabled'), 403);
    }

    if (!checkRateLimit('user_add_student', 20, 60)) {
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

        $matrik = trim((string)($data['matrik'] ?? ''));
        $scope = strtolower(trim((string)($data['scope'] ?? 'student')));
        $groupID = (int)($data['groupID'] ?? 0);
        $flag = isset($data['flag']) ? (int)$data['flag'] : 1;

        if ($scope !== 'student' && $scope !== 'pelajar') {
            jsonErrorResponse((string)__('userList_ajax_student_scope_only'), 400);
        }

        if ($matrik === '') {
            jsonErrorResponse((string)__('userList_ajax_matric_required'), 400);
        }

        if (!in_array($flag, [0, 1], true)) {
            $flag = 1;
        }

        return [
            'matrik' => $matrik,
            'groupID' => $groupID,
            'flag' => $flag,
        ];
    };

    $resolveGroup = static function (PDO $pdo, int $groupID): array {
        if ($groupID <= 0) {
            jsonErrorResponse((string)__('userList_ajax_invalid_group'), 400);
        }

        $sql = "SELECT f_groupID, f_groupKod, f_categoryUser FROM tbl_m_group WHERE f_groupID = :groupID LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':groupID' => $groupID]);
        $groupRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$groupRow) {
            jsonErrorResponse((string)__('userList_ajax_invalid_group'), 400);
        }

        if (strtoupper(trim((string)($groupRow['f_categoryUser'] ?? ''))) !== 'PELAJAR') {
            jsonErrorResponse((string)__('userList_ajax_invalid_student_group'), 400);
        }

        return [
            'groupID' => (int)($groupRow['f_groupID'] ?? 0),
            'groupKod' => (string)($groupRow['f_groupKod'] ?? ''),
        ];
    };

    $ensureUserNotExists = static function (PDO $pdo, string $identifier): void {
        $sql = "SELECT f_userID
                FROM tbl_m_user
                WHERE f_stafID = :stafID
                   OR TRIM(COALESCE(f_loginID, '')) = :loginID
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':stafID' => $identifier,
            ':loginID' => $identifier,
        ]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonErrorResponse((string)__('userList_ajax_student_exists'), 409);
        }
    };

    $fetchStudent = static function (string $matrik): array {
        $pdoSybase = Database::pdoSybaseStudent();
        if (!$pdoSybase) {
            jsonErrorResponse((string)__('studentSearch_mode_disabled'), 403);
        }

        $sql = "
            SELECT
                matrik,
                nama,
                nokp,
                email,
                hpno,
                telno,
                telno_terkini,
                notel_terkini,
                kdprogram,
                program,
                kdfakulti,
                fakulti,
                kdtahap,
                tahap_pengajian,
                kadet,
                kategori_kadet,
                status,
                statusketerangan,
                statuskategori
            FROM v210
            WHERE convert(varchar(50), matrik) = :matrik
              AND upper(convert(varchar(20), statuskategori)) = 'AKTIF'
        ";
        $stmt = $pdoSybase->prepare($sql);
        $stmt->bindValue(':matrik', $matrik);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonErrorResponse((string)__('userList_ajax_student_source_not_found'), 404);
        }

        return $row;
    };

    $derivePhone = static function (array $student): ?string {
        $candidates = [
            trim((string)($student['notel_terkini'] ?? '')),
            trim((string)($student['hpno'] ?? '')),
            trim((string)($student['telno_terkini'] ?? '')),
            trim((string)($student['telno'] ?? '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return null;
    };

    $deriveAuditUserId = static function (): ?int {
        if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
            return (int)$_SESSION['user']['f_userID'];
        }
        if (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
            return (int)$_SESSION['f_userID'];
        }
        return null;
    };

    $fetchInsertedUserRow = static function (PDO $pdo, int $userID): array {
        $sql = "
            SELECT
                u.f_userID,
                u.f_loginID,
                u.f_stafID,
                u.f_nickname,
                u.f_email,
                u.f_handphone,
                u.f_nokp,
                u.f_nopekerja,
                u.f_nama,
                u.f_categoryUser,
                u.f_namajabatan,
                u.f_jawatan,
                u.f_flag,
                u.f_groupID,
                u.f_groupKod,
                COALESCE(NULLIF(TRIM(g.f_groupName), ''), TRIM(u.f_groupKod)) AS f_groupName
            FROM tbl_m_user u
            LEFT JOIN tbl_m_group g ON g.f_groupID = u.f_groupID
            WHERE u.f_userID = :userID
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':userID' => $userID]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    };

    $insertStudent = static function (
        PDO $pdo,
        array $student,
        int $groupID,
        string $groupKod,
        int $flag,
        ?string $loggedInStafID,
        callable $derivePhone,
        User $userSchema
    ): int {
        $nokp = trim((string)($student['nokp'] ?? ''));
        $hashedPassword = $nokp !== '' ? password_hash($nokp, PASSWORD_DEFAULT) : '';
        $kumpjawatan = trim((string)($student['kadet'] ?? ''));
        $columnValueMap = [
            'f_loginID' => trim((string)($student['matrik'] ?? '')),
            'f_stafID' => trim((string)($student['matrik'] ?? '')),
            'f_categoryUser' => 'PELAJAR',
            'f_nopekerja' => null,
            'f_nama' => trim((string)($student['nama'] ?? '')) ?: null,
            'f_nickname' => trim((string)($student['nama'] ?? '')) ?: null,
            'f_nokp' => $nokp !== '' ? $nokp : null,
            'f_password' => $hashedPassword,
            'f_email' => trim((string)($student['email'] ?? '')) ?: null,
            'f_handphone' => $derivePhone($student),
            'f_jawatanKod' => trim((string)($student['kdprogram'] ?? '')) ?: null,
            'f_jawatan' => trim((string)($student['program'] ?? '')) ?: null,
            'f_jenisID' => trim((string)($student['kdtahap'] ?? '')) ?: null,
            'f_jenis' => trim((string)($student['tahap_pengajian'] ?? '')) ?: null,
            'f_jabatanKod' => trim((string)($student['kdfakulti'] ?? '')) ?: null,
            'f_namajabatan' => trim((string)($student['fakulti'] ?? '')) ?: null,
            'f_kumpjawatan' => $kumpjawatan !== '' ? $kumpjawatan : null,
            'f_verified_at' => '__SQL_NOW__',
            'f_must_change_password' => 1,
            'f_password_changed_at' => null,
            'f_password_expires_at' => null,
            'f_statusID' => null,
            'f_status' => trim((string)($student['statuskategori'] ?? '')) ?: null,
            'f_groupID' => $groupID,
            'f_groupKod' => $groupKod,
            'f_flag' => $flag,
            'f_insertdt' => '__SQL_NOW__',
            'f_updatedt' => '__SQL_NOW__',
            'f_updateby' => $loggedInStafID,
            'f_remarks' => 'Added via Tambah Pelajar form',
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

        $sql = "INSERT INTO tbl_m_user (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        $ok = $stmt->execute();

        if (!$ok) {
            throw new RuntimeException('Gagal menyimpan data pelajar.');
        }

        return (int)$pdo->lastInsertId();
    };

    $logStudentAudit = static function (array $student, int $groupID, string $groupKod, int $flag, int $newUserId) use ($deriveAuditUserId): void {
        if (!function_exists('audit_event')) {
            return;
        }

        $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
        $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
        $formattedActorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label($nama, $nostaf)
            : $nama;

        audit_event([
            'event_type' => 'CREATE',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'user',
            'target_id' => (string)($student['matrik'] ?? ''),
            'target_label' => 'Student: ' . trim((string)($student['nama'] ?? $student['matrik'] ?? '')),
            'message' => audit_format_message('Student user created from v210 data', $formattedActorLabel),
            'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
            'session_id' => session_id() ?: null,
            'user_id' => $deriveAuditUserId(),
            'actor_label' => $formattedActorLabel,
            'meta' => [
                'groupID' => $groupID,
                'groupKod' => $groupKod,
                'flag' => $flag,
                'source' => 'user_add_student_ajax',
                'userID' => $newUserId,
                'category' => 'PELAJAR',
            ],
        ]);
    };

    $clearStudentCaches = static function (): void {
        if (isset($_SESSION['userlist_cache']) && is_array($_SESSION['userlist_cache'])) {
            foreach (array_keys($_SESSION['userlist_cache']) as $key) {
                if (str_starts_with((string)$key, 'student_options')) {
                    unset($_SESSION['userlist_cache'][$key]);
                }
            }
        }
    };

    $payload = $readPayload();
    $matrik = $payload['matrik'];
    $group = $resolveGroup($pdo, $payload['groupID']);
    userListEnsureAssignableGroup($pdo, (int)$payload['groupID']);
    $ensureUserNotExists($pdo, $matrik);
    $student = $fetchStudent($matrik);

    $loggedInStafID = $_SESSION['f_stafID'] ?? null;
    $newUserId = $insertStudent($pdo, $student, $group['groupID'], $group['groupKod'], $payload['flag'], $loggedInStafID, $derivePhone, $userSchema);
    $insertedRow = $fetchInsertedUserRow($pdo, $newUserId);

    try {
        $logStudentAudit($student, $group['groupID'], $group['groupKod'], $payload['flag'], $newUserId);
    } catch (Throwable $e) {
        error_log('[user-add-student] Audit logging failed: ' . $e->getMessage());
    }

    $clearStudentCaches();

    jsonSuccessResponse([
        'message' => (string)__('userList_success_add_student'),
        'userID' => $newUserId,
        'row' => $insertedRow,
    ]);
} catch (PDOException $e) {
    error_log('[user-add-student] PDO Error: ' . $e->getMessage());
    if (isset($sql)) {
        error_log('[user-add-student] Last SQL: ' . $sql);
    }
    if (isset($params) && is_array($params)) {
        error_log('[user-add-student] Param keys: ' . implode(', ', array_keys($params)));
    }
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
        jsonErrorResponse((string)__('userList_ajax_student_exists'), 409);
    }
    jsonErrorResponse((string)__('userList_ajax_database_error'), 500);
} catch (Throwable $e) {
    error_log('[user-add-student] Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_add_student_system_error'), 500);
}

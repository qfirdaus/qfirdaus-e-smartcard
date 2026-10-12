<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-add-public.php
// Public/manual add flow into tbl_m_user
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    logAjaxUnexpectedOutput('user-add-public:init.php', $initOutput);

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

    if (!checkRateLimit('user_add_public', 20, 60)) {
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

        $scope = strtolower(trim((string)($data['scope'] ?? 'public')));
        $name = trim((string)($data['name'] ?? ''));
        $nickname = trim((string)($data['nickname'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $phone = trim((string)($data['phone'] ?? ''));
        $university = trim((string)($data['university'] ?? ''));
        $nokp = trim((string)($data['nokp'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $passwordConfirm = (string)($data['password_confirm'] ?? '');
        $groupID = (int)($data['groupID'] ?? 0);
        $flag = isset($data['flag']) ? (int)$data['flag'] : 1;

        if ($scope !== 'public' && $scope !== 'umum') {
            jsonErrorResponse((string)__('userList_ajax_public_scope_only'), 400);
        }

        if ($name === '') {
            jsonErrorResponse((string)__('userList_ajax_name_required'), 400);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonErrorResponse((string)__('userList_ajax_invalid_email'), 400);
        }

        if ($password === '' || strlen($password) < 6) {
            jsonErrorResponse((string)__('userList_ajax_password_min'), 400);
        }

        if ($passwordConfirm !== $password) {
            jsonErrorResponse((string)__('userList_ajax_password_confirm_mismatch'), 400);
        }

        if (!in_array($flag, [0, 1], true)) {
            $flag = 1;
        }

        return [
            'name' => $name,
            'nickname' => $nickname,
            'email' => $email,
            'phone' => $phone,
            'university' => $university,
            'nokp' => $nokp,
            'password' => $password,
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

        if (strtoupper(trim((string)($groupRow['f_categoryUser'] ?? ''))) !== 'UMUM') {
            jsonErrorResponse((string)__('userList_ajax_invalid_public_group'), 400);
        }

        return [
            'groupID' => (int)($groupRow['f_groupID'] ?? 0),
            'groupKod' => (string)($groupRow['f_groupKod'] ?? ''),
        ];
    };

    $ensureUserNotExists = static function (PDO $pdo, string $email): void {
        $sql = "SELECT f_userID
                FROM tbl_m_user
                WHERE TRIM(COALESCE(f_loginID, '')) = :loginID
                   OR LOWER(TRIM(COALESCE(f_email, ''))) = :email
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':loginID' => $email,
            ':email' => $email,
        ]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonErrorResponse((string)__('userList_ajax_email_exists'), 409);
        }
    };

    $generateLegacyPublicId = static function (PDO $pdo): string {
        for ($i = 0; $i < 15; $i++) {
            $candidate = 'PUB' . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT f_userID FROM tbl_m_user WHERE f_stafID = :legacyID LIMIT 1");
            $stmt->execute([':legacyID' => $candidate]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Gagal menjana identifier pengguna umum.');
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

    $insertPublicUser = static function (
        PDO $pdo,
        array $payload,
        int $groupID,
        string $groupKod,
        int $flag,
        ?string $loggedInStafID,
        callable $generateLegacyPublicId,
        User $userSchema
    ): int {
        $nickname = $payload['nickname'] !== '' ? $payload['nickname'] : $payload['name'];
        $hashedPassword = password_hash($payload['password'], PASSWORD_DEFAULT);
        $legacyPublicId = $generateLegacyPublicId($pdo);
        $columnValueMap = [
            'f_loginID' => $payload['email'],
            'f_stafID' => $legacyPublicId,
            'f_categoryUser' => 'UMUM',
            'f_nopekerja' => null,
            'f_nama' => $payload['name'],
            'f_nickname' => $nickname,
            'f_nokp' => $payload['nokp'] !== '' ? $payload['nokp'] : null,
            'f_password' => $hashedPassword,
            'f_email' => $payload['email'],
            'f_handphone' => $payload['phone'] !== '' ? $payload['phone'] : null,
            'f_namajabatan' => $payload['university'] !== '' ? $payload['university'] : null,
            'f_verified_at' => '__SQL_NOW__',
            'f_must_change_password' => 1,
            'f_password_changed_at' => null,
            'f_password_expires_at' => null,
            'f_statusID' => null,
            'f_status' => $flag === 1 ? 'AKTIF' : 'DISAHKAN',
            'f_groupID' => $groupID,
            'f_groupKod' => $groupKod,
            'f_flag' => $flag,
            'f_insertdt' => '__SQL_NOW__',
            'f_updatedt' => '__SQL_NOW__',
            'f_updateby' => $loggedInStafID,
            'f_remarks' => 'Added via Tambah Umum form',
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
        $ok = $stmt->execute($params);

        if (!$ok) {
            throw new RuntimeException('Gagal menyimpan data pengguna umum.');
        }

        return (int)$pdo->lastInsertId();
    };

    $logPublicAudit = static function (array $payload, int $groupID, string $groupKod, int $flag, int $newUserId): void {
        if (!function_exists('audit_event')) {
            return;
        }

        $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
        $loginID = $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ($_SESSION['f_stafID'] ?? null);
        $formattedActorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label($nama, $loginID)
            : $nama;
        $message = function_exists('audit_format_message')
            ? audit_format_message('Public user created manually', $formattedActorLabel)
            : 'Public user created manually';

        audit_event([
            'event_type' => 'CREATE',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'user',
            'target_id' => (string)$payload['email'],
            'target_label' => 'Public User: ' . $payload['name'],
            'message' => $message,
            'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
            'session_id' => session_id() ?: null,
            'user_id' => !empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID']) ? (int)$_SESSION['f_userID'] : null,
            'actor_label' => $formattedActorLabel,
            'meta' => [
                'groupID' => $groupID,
                'groupKod' => $groupKod,
                'flag' => $flag,
                'source' => 'user_add_public_ajax',
                'target_userID' => $newUserId,
                'target_loginID' => $payload['email'],
                'target_category' => 'UMUM',
                'target_stafID' => null,
                'email' => $payload['email'],
                'university' => $payload['university'] ?? '',
            ],
        ]);
    };

    $payload = $readPayload();
    $group = $resolveGroup($pdo, $payload['groupID']);
    userListEnsureAssignableGroup($pdo, (int)$payload['groupID']);
    $ensureUserNotExists($pdo, $payload['email']);

    $loggedInStafID = $_SESSION['f_stafID'] ?? null;
    $newUserId = $insertPublicUser($pdo, $payload, $group['groupID'], $group['groupKod'], $payload['flag'], $loggedInStafID, $generateLegacyPublicId, $userSchema);
    $insertedRow = $fetchInsertedUserRow($pdo, $newUserId);

    try {
        $logPublicAudit($payload, $group['groupID'], $group['groupKod'], $payload['flag'], $newUserId);
    } catch (Throwable $e) {
        error_log('[user-add-public] Audit logging failed: ' . $e->getMessage());
    }

    jsonSuccessResponse([
        'message' => (string)__('userList_ajax_success_add_public'),
        'userID' => $newUserId,
        'row' => $insertedRow,
    ]);
} catch (PDOException $e) {
    error_log('[user-add-public] PDO Error: ' . $e->getMessage());
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
        jsonErrorResponse((string)__('userList_ajax_email_exists'), 409);
    }
    jsonErrorResponse((string)__('userList_ajax_database_error'), 500);
} catch (Throwable $e) {
    error_log('[user-add-public] Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_add_public_system_error'), 500);
}

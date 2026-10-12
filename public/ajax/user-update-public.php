<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    logAjaxUnexpectedOutput('user-update-public:init.php', $initOutput);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonErrorResponse((string)__('userList_ajax_method_not_allowed'), 405);
    }

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    require_once __DIR__ . '/../classes/Database.php';
    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    if (!checkRateLimit('user_update_public', 30, 60)) {
        jsonErrorResponse((string)__('userList_ajax_rate_limited'), 429);
    }

    $csrfHeader = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isValidCsrfToken($csrfHeader)) {
        jsonErrorResponse((string)__('userGroup_csrf_invalid'), 400);
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        jsonErrorResponse((string)__('userList_ajax_invalid_data'), 400);
    }

    $userID = (int)($data['userID'] ?? 0);
    $groupID = (int)($data['groupID'] ?? 0);
    $flag = isset($data['flag']) ? (int)$data['flag'] : 1;
    $name = trim((string)($data['name'] ?? ''));
    $nickname = trim((string)($data['nickname'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $phone = trim((string)($data['phone'] ?? ''));
    $university = trim((string)($data['university'] ?? ''));
    $nokp = trim((string)($data['nokp'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $passwordConfirm = (string)($data['password_confirm'] ?? '');

    if ($userID <= 0) {
        jsonErrorResponse((string)__('userList_ajax_invalid_user_param'), 422);
    }
    if ($name === '') {
        jsonErrorResponse((string)__('userList_ajax_name_required'), 400);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonErrorResponse((string)__('userList_ajax_invalid_email'), 400);
    }
    if (!in_array($flag, [0, 1], true)) {
        $flag = 1;
    }
    if ($password !== '' && strlen($password) < 6) {
        jsonErrorResponse((string)__('userList_ajax_password_min'), 400);
    }
    if (($password !== '' || $passwordConfirm !== '') && $password !== $passwordConfirm) {
        jsonErrorResponse((string)__('userList_ajax_password_confirm_mismatch'), 400);
    }

    $stmtUser = $pdo->prepare("
        SELECT f_userID, f_categoryUser, f_stafID, f_loginID, f_email
        FROM tbl_m_user
        WHERE f_userID = :userID
        LIMIT 1
    ");
    $stmtUser->execute([':userID' => $userID]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) {
        jsonErrorResponse((string)__('userList_ajax_user_not_found'), 404);
    }
    userListEnsureTargetUserEditable($pdo, $userID);
    $targetProtectedStaffId = (string)($userRow['f_stafID'] ?? '');
    if (isProtectedStaffAccount($targetProtectedStaffId) && !canSelfManageProtectedStaffAccount($targetProtectedStaffId)) {
        try {
            audit_event([
                'event_type' => 'UPDATE',
                'severity' => 'WARN',
                'outcome' => 'DENIED',
                'target_type' => 'user',
                'target_id' => (string)$userID,
                'target_label' => (string)($userRow['f_loginID'] ?? $userRow['f_stafID'] ?? 'Protected User'),
                'message' => 'UPDATE_PUBLIC_USER blocked (protected account)',
                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                'session_id' => session_id() ?: null,
                'user_id' => !empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID']) ? (int)$_SESSION['f_userID'] : null,
                'actor_label' => ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null),
                'meta' => [
                    'reason' => 'protected_staff_account',
                    'protected_stafID' => (string)($userRow['f_stafID'] ?? ''),
                    'performed_by' => (string)($_SESSION['f_stafID'] ?? ''),
                ],
            ]);
        } catch (Throwable $auditError) {
            error_log('[user-update-public] Protected account audit logging failed: ' . $auditError->getMessage());
        }

    jsonErrorResponse((string)__('userList_protected_self_manage_only'), 403);
    }
    if (strtoupper(trim((string)($userRow['f_categoryUser'] ?? ''))) !== 'UMUM') {
        jsonErrorResponse((string)__('userList_ajax_public_only'), 400);
    }

    $stmtGroup = $pdo->prepare("
        SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser
        FROM tbl_m_group
        WHERE f_groupID = :groupID
        LIMIT 1
    ");
    $stmtGroup->execute([':groupID' => $groupID]);
    $groupRow = $stmtGroup->fetch(PDO::FETCH_ASSOC);
    if (!$groupRow) {
        jsonErrorResponse((string)__('userList_ajax_invalid_group'), 400);
    }
    userListEnsureAssignableGroup($pdo, $groupID);
    if (strtoupper(trim((string)($groupRow['f_categoryUser'] ?? ''))) !== 'UMUM') {
        jsonErrorResponse((string)__('userList_ajax_invalid_public_group'), 400);
    }

    $stmtDup = $pdo->prepare("
        SELECT f_userID
        FROM tbl_m_user
        WHERE f_userID <> :userID
          AND (
            TRIM(COALESCE(f_loginID, '')) = :loginID
            OR LOWER(TRIM(COALESCE(f_email, ''))) = :email
          )
        LIMIT 1
    ");
    $stmtDup->execute([
        ':userID' => $userID,
        ':loginID' => $email,
        ':email' => $email,
    ]);
    if ($stmtDup->fetch(PDO::FETCH_ASSOC)) {
        jsonErrorResponse((string)__('userList_ajax_email_exists'), 409);
    }

    $setParts = [
        'f_loginID = :loginID',
        'f_nama = :nama',
        'f_nickname = :nickname',
        'f_nokp = :nokp',
        'f_email = :email',
        'f_handphone = :handphone',
        'f_namajabatan = :namajabatan',
        'f_groupID = :groupID',
        'f_groupKod = :groupKod',
        'f_flag = :flag',
        'f_status = :status',
        'f_updatedt = NOW()',
        'f_updateby = :updateby',
    ];
    $params = [
        ':userID' => $userID,
        ':loginID' => $email,
        ':nama' => $name,
        ':nickname' => ($nickname !== '' ? $nickname : $name),
        ':nokp' => ($nokp !== '' ? $nokp : null),
        ':email' => $email,
        ':handphone' => ($phone !== '' ? $phone : null),
        ':namajabatan' => ($university !== '' ? $university : null),
        ':groupID' => (int)$groupRow['f_groupID'],
        ':groupKod' => (string)$groupRow['f_groupKod'],
        ':flag' => $flag,
        ':status' => $flag === 1 ? 'AKTIF' : 'DISAHKAN',
        ':updateby' => (string)($_SESSION['f_stafID'] ?? ''),
    ];

    if ($password !== '') {
        $setParts[] = 'f_password = :password';
        $setParts[] = 'f_verified_at = COALESCE(f_verified_at, NOW())';
        $setParts[] = 'f_must_change_password = 1';
        $setParts[] = 'f_password_changed_at = NULL';
        $setParts[] = 'f_password_expires_at = NULL';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql = "UPDATE tbl_m_user SET " . implode(', ', $setParts) . " WHERE f_userID = :userID";
    $stmtUpdate = $pdo->prepare($sql);
    $stmtUpdate->execute($params);

    if (function_exists('audit_event')) {
        try {
            $actorName = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
            $actorLoginID = $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ($_SESSION['f_stafID'] ?? null);
            $actorLabel = function_exists('audit_format_actor_label')
                ? audit_format_actor_label($actorName, $actorLoginID)
                : (string)($actorName ?? '');
            audit_event([
                'event_type' => 'UPDATE',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'user',
                'target_id' => (string)$userID,
                'target_label' => 'Public User: ' . $name,
                'message' => function_exists('audit_format_message')
                    ? audit_format_message('Public user updated manually', $actorLabel)
                    : 'Public user updated manually',
                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                'session_id' => session_id() ?: null,
                'user_id' => !empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID']) ? (int)$_SESSION['f_userID'] : null,
                'actor_label' => $actorLabel,
                'meta' => [
                    'target_userID' => $userID,
                    'target_loginID' => $email,
                    'target_category' => 'UMUM',
                    'email' => $email,
                    'university' => $university,
                    'groupID' => (int)$groupRow['f_groupID'],
                    'groupKod' => (string)$groupRow['f_groupKod'],
                    'flag' => $flag,
                    'password_reset' => $password !== '',
                    'source' => 'user_update_public_ajax',
                ],
            ]);
        } catch (Throwable $auditError) {
            error_log('[user-update-public] Audit logging failed: ' . $auditError->getMessage());
        }
    }

    jsonSuccessResponse([
        'message' => (string)__('userList_success_update_public'),
        'group' => [
            'id' => (int)$groupRow['f_groupID'],
            'kod' => (string)$groupRow['f_groupKod'],
            'nama' => (string)($groupRow['f_groupName'] ?? $groupRow['f_groupKod']),
        ],
        'user' => [
            'id' => $userID,
            'name' => $name,
            'nickname' => ($nickname !== '' ? $nickname : $name),
            'loginID' => $email,
            'email' => $email,
            'phone' => $phone,
            'university' => $university,
            'nokp' => $nokp,
        ],
    ]);
} catch (PDOException $e) {
    error_log('[user-update-public] PDO Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
} catch (Throwable $e) {
    error_log('[user-update-public] Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
}

<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-set-group.php
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
    logAjaxUnexpectedOutput('user-set-group:init.php', $initOutput);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonErrorResponse((string)__('userList_ajax_method_not_allowed'), 405);
    }

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    require_once __DIR__ . '/../classes/Database.php';
    
    // Rate limiting: max 30 requests per 60 seconds
    if (!checkRateLimit('user_set_group', 30, 60)) {
        json_fail((string)__('userList_ajax_rate_limited'), 429);
    }

    $permDb = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($permDb);
} catch (Throwable $initError) {
    error_log('[user-set-group] Init Error: '.$initError->getMessage().' in '.$initError->getFile().':'.$initError->getLine());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
}

function json_fail(string $msg, int $code = 400, array $extra = []): never {
    jsonErrorResponse($msg, $code);
}
function json_ok(array $data = []): never {
    jsonSuccessResponse($data);
}
function actor_id(): int {
    return (int)($_SESSION['auth']['f_userID'] ?? $_SESSION['user']['f_userID'] ?? $_SESSION['user_id'] ?? 0);
}
function actor_name(): string {
    return (string)($_SESSION['auth']['f_nama'] ?? $_SESSION['user']['f_nama'] ?? $_SESSION['d_name'] ?? '');
}

function readUserSetGroupPayload(): array {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: [];

    $userID = (int)($data['userID'] ?? 0);
    $groupID = (int)($data['groupID'] ?? 0);
    $hasFlag = array_key_exists('flag', $data);
    $flag = $hasFlag ? (int)$data['flag'] : null;
    $hasGroup = ($groupID > 0);
    $password = (string)($data['password'] ?? '');
    $passwordConfirm = (string)($data['password_confirm'] ?? '');

    if ($userID <= 0) {
        json_fail((string)__('userList_ajax_incomplete_params_userid'), 422);
    }

    if (!$hasGroup && !$hasFlag) {
        json_fail((string)__('userList_ajax_incomplete_params_group_or_flag'), 422);
    }

    return [
        'data' => $data,
        'userID' => $userID,
        'groupID' => $groupID,
        'hasGroup' => $hasGroup,
        'hasFlag' => $hasFlag,
        'flag' => $flag,
        'password' => $password,
        'passwordConfirm' => $passwordConfirm,
    ];
}

function userTableHasColumn(PDO $db, string $table, string $col): bool {
    $q = $db->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
        LIMIT 1
    ");
    $q->execute([':t' => $table, ':c' => $col]);
    return (bool)$q->fetchColumn();
}

function getUserGroupSchemaFlags(PDO $db): array {
    $hasGroupID = userTableHasColumn($db, 'tbl_m_user', 'f_groupID');
    $hasGroupKod = userTableHasColumn($db, 'tbl_m_user', 'f_groupKod');

    if (!$hasGroupID && !$hasGroupKod) {
        json_fail((string)__('userList_ajax_user_schema_invalid'), 500);
    }

    return [
        'hasGroupID' => $hasGroupID,
        'hasGroupKod' => $hasGroupKod,
    ];
}

function fetchUserGroupContext(PDO $db, int $userID, bool $hasGroupID, bool $hasGroupKod): array {
    $selCols = "u.f_userID, u.f_nama, u.f_stafID, COALESCE(u.f_categoryUser, '') AS user_category, u.f_flag AS old_flag";
    $selCols .= $hasGroupID ? ", u.f_groupID AS old_groupID" : ", NULL AS old_groupID";
    $selCols .= $hasGroupKod ? ", u.f_groupKod AS old_groupKod" : ", NULL AS old_groupKod";

    if ($hasGroupID) {
        $join = "LEFT JOIN tbl_m_group g ON g.f_groupID = u.f_groupID";
    } elseif ($hasGroupKod) {
        $join = "LEFT JOIN tbl_m_group g ON g.f_groupKod = u.f_groupKod";
    } else {
        $join = "";
    }

    $sqlUser = "
        SELECT $selCols, g.f_groupName AS old_groupName
        FROM tbl_m_user u
        $join
        WHERE u.f_userID = :uid
        LIMIT 1
    ";
    $stmtU = $db->prepare($sqlUser);
    $stmtU->execute([':uid' => $userID]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_fail((string)__('userList_ajax_user_not_found'), 404);
    }

    return [
        'user' => $user,
        'userCategory' => strtoupper(trim((string)($user['user_category'] ?? ''))),
        'oldID' => (int)($user['old_groupID'] ?? 0),
        'oldKod' => (string)($user['old_groupKod'] ?? ''),
        'oldName' => (string)($user['old_groupName'] ?? (($user['old_groupKod'] ?? '') ?: ((int)($user['old_groupID'] ?? 0) ?: ''))),
        'oldFlag' => (int)($user['old_flag'] ?? 0),
    ];
}

function resolveTargetGroup(PDO $db, bool $hasGroup, int $groupID, int $oldID, string $oldKod, string $oldName): array {
    if (!$hasGroup) {
        return ['gid' => $oldID, 'gkod' => $oldKod, 'gnam' => $oldName];
    }

    $stmt = $db->prepare("SELECT f_groupID, f_groupKod, f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
    $stmt->execute([':gid' => $groupID]);
    $grp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$grp) {
        json_fail((string)__('userList_ajax_group_not_found'), 404);
    }

    return [
        'gid' => (int)$grp['f_groupID'],
        'gkod' => (string)$grp['f_groupKod'],
        'gnam' => (string)$grp['f_groupName'],
    ];
}

function evaluateNoopState(
    bool $hasGroup,
    bool $hasFlag,
    bool $hasPassword,
    bool $hasGroupID,
    bool $hasGroupKod,
    int $oldID,
    string $oldKod,
    int $oldFlag,
    int $gid,
    string $gkod,
    ?int $flag
): array {
    $groupSame = !$hasGroup;
    if ($hasGroup) {
        if ($hasGroupID) {
            $groupSame = ($oldID === $gid);
        } elseif ($hasGroupKod && $oldKod !== '') {
            $groupSame = ($oldKod === $gkod);
        }
    }

    $flagSame = !$hasFlag || ($oldFlag === $flag);
    $same = $groupSame && $flagSame && !$hasPassword;

    error_log("[user-set-group] No-op check: hasGroup=" . ($hasGroup ? 'true' : 'false') . ", hasFlag=" . ($hasFlag ? 'true' : 'false') . ", hasPassword=" . ($hasPassword ? 'true' : 'false') . ", groupSame=" . ($groupSame ? 'true' : 'false') . ", flagSame=" . ($flagSame ? 'true' : 'false') . ", same=" . ($same ? 'true' : 'false'));
    error_log("[user-set-group] Values: oldKod='$oldKod', gkod='$gkod', oldFlag=$oldFlag, flag=" . ($flag !== null ? (string)$flag : 'null'));

    return [
        'groupSame' => $groupSame,
        'flagSame' => $flagSame,
        'same' => $same,
    ];
}

function buildCommonAuditMeta(array $user): array {
    return [
        'stafID' => (string)($user['f_stafID'] ?? ''),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'csrf_hash' => substr(hash('sha256', (string)($_SESSION['csrf_token'] ?? '')), 0, 16),
        'route' => $_SERVER['REQUEST_URI'] ?? 'ajax/user-set-group.php',
    ];
}

function auditUserSetGroupNoop(
    array $user,
    array $metaCommon,
    bool $hasGroup,
    bool $hasFlag,
    bool $hasGroupID,
    bool $hasGroupKod,
    int $oldID,
    int $gid,
    string $oldKod,
    string $gkod,
    int $oldFlag,
    ?int $flag
): int {
    $eventId = audit_event([
        'type' => 'user.group.update',
        'action' => 'UPDATE',
        'status' => 'noop',
        'actor_id' => actor_id(),
        'actor_name' => actor_name(),
        'target_type' => 'user',
        'target_id' => (string)$user['f_userID'],
        'target_name' => (string)$user['f_nama'],
        'meta' => $metaCommon,
    ]) ?? 0;

    if ($eventId) {
        $csId = audit_begin_change($eventId, 'user', (string)$user['f_userID'], 'No change', ['context' => 'set-group']) ?? 0;
        if ($csId) {
            if ($hasGroup && $hasGroupID) {
                audit_change($csId, 'f_groupID', $oldID, $gid, 'number', false, 'noop');
            }
            if ($hasGroup && $hasGroupKod) {
                audit_change($csId, 'f_groupKod', $oldKod, $gkod, 'string', false, 'noop');
            }
            if (!$hasGroup && $hasFlag) {
                audit_change($csId, 'f_flag', $oldFlag, $flag, 'number', false, 'noop');
            }
        }
    }

    return $eventId;
}

function buildUserUpdateStatement(
    bool $hasGroup,
    bool $hasFlag,
    bool $hasGroupID,
    bool $hasGroupKod,
    int $userID,
    int $gid,
    string $gkod,
    ?int $flag,
    string $password = '',
    string $passwordHash = ''
): array {
    $setParts = [];
    $params = [':uid' => $userID];

    if ($hasGroup) {
        if ($hasGroupID && $hasGroupKod) {
            $setParts[] = "f_groupID = :gid";
            $setParts[] = "f_groupKod = :gkod";
            $params[':gid'] = $gid;
            $params[':gkod'] = $gkod;
            error_log("[user-set-group] Group update: userID=$userID, groupID=$gid, groupKod=$gkod");
        } elseif ($hasGroupID) {
            $setParts[] = "f_groupID = :gid";
            $params[':gid'] = $gid;
            error_log("[user-set-group] Group update: userID=$userID, groupID=$gid");
        } elseif ($hasGroupKod) {
            $setParts[] = "f_groupKod = :gkod";
            $params[':gkod'] = $gkod;
            error_log("[user-set-group] Group update: userID=$userID, groupKod=$gkod");
        }
    }

    if ($hasFlag) {
        $setParts[] = "f_flag = :flag";
        $params[':flag'] = $flag;
        error_log("[user-set-group] Flag update: userID=$userID, flag=$flag, hasGroup=" . ($hasGroup ? 'true' : 'false'));
    }

    if ($password !== '') {
        $setParts[] = "f_password = :password";
        $setParts[] = "f_verified_at = COALESCE(f_verified_at, NOW())";
        $setParts[] = "f_must_change_password = 1";
        $setParts[] = "f_password_changed_at = NULL";
        $setParts[] = "f_password_expires_at = NULL";
        $params[':password'] = $passwordHash;
        error_log("[user-set-group] Password reset update: userID=$userID");
    }

    if (empty($setParts)) {
        error_log("[user-set-group] ERROR: setParts is empty! hasGroup=" . ($hasGroup ? 'true' : 'false') . ", hasFlag=" . ($hasFlag ? 'true' : 'false'));
        json_fail((string)__('userList_ajax_no_fields_update'), 422);
    }

    error_log("[user-set-group] Final setParts: " . json_encode($setParts) . ", params keys: " . json_encode(array_keys($params)));

    return [
        'setParts' => $setParts,
        'params' => $params,
        'sql' => "UPDATE tbl_m_user SET " . implode(', ', $setParts) . " WHERE f_userID = :uid",
    ];
}

function executeUserUpdate(PDO $db, string $sql, array $params, int $userID): void {
    error_log("[user-set-group] ===== EXECUTING UPDATE =====");
    error_log("[user-set-group] SQL: $sql");
    error_log("[user-set-group] Params: " . json_encode($params));
    error_log("[user-set-group] setParts count: " . (count($params) - 1));

    try {
        $upd = $db->prepare($sql);
        $result = $upd->execute($params);
        $rowsAffected = $upd->rowCount();
        error_log("[user-set-group] Execute result: " . ($result ? 'SUCCESS' : 'FAILED'));
        error_log("[user-set-group] Rows affected: $rowsAffected");

        if (!$result) {
            $errorInfo = $upd->errorInfo();
            error_log("[user-set-group] PDO Error: " . json_encode($errorInfo));
            json_fail((string)__('userList_ajax_update_record_failed'), 500);
        }

        if ($rowsAffected === 0) {
            error_log("[user-set-group] WARNING: No rows affected! Checking if user exists...");
            $checkStmt = $db->prepare("SELECT f_userID, f_flag FROM tbl_m_user WHERE f_userID = :uid");
            $checkStmt->execute([':uid' => $userID]);
            $checkUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
            error_log("[user-set-group] User check: " . json_encode($checkUser));

            if (!$checkUser) {
                json_fail((string)__('userList_ajax_user_not_found_db'), 404);
            }

            error_log("[user-set-group] User exists but no rows affected - possible no-op (value already set)");
        }
    } catch (PDOException $e) {
        error_log("[user-set-group] PDO Exception: " . $e->getMessage());
        error_log("[user-set-group] SQL: $sql");
        error_log("[user-set-group] Params: " . json_encode($params));
        json_fail((string)__('userList_ajax_system_error'), 500);
    }
}

function deriveUserSetGroupAuditUserId(PDO $db): ?int {
    if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
        return (int)$_SESSION['user']['f_userID'];
    }
    if (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
        return (int)$_SESSION['f_userID'];
    }

    $cand = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null;
    if ($cand) {
        if (is_numeric($cand)) {
            return (int)$cand;
        }
        if (preg_match('/^(\d+)/', (string)$cand, $m)) {
            return (int)$m[1];
        }
    }

    if (!empty($_SESSION['f_stafID'])) {
        try {
            $up = (new User($db))->getProfile($_SESSION['f_stafID']);
            if (!empty($up['f_nopekerja'])) {
                $c = $up['f_nopekerja'];
                if (is_numeric($c)) {
                    return (int)$c;
                }
                if (preg_match('/^(\d+)/', (string)$c, $m2)) {
                    return (int)$m2[1];
                }
            }
        } catch (Throwable $e) {
            error_log('[user-set-group] user_id derivation DB lookup failed: ' . $e->getMessage());
        }
    }

    return null;
}

function auditUserSetGroupUpdate(
    PDO $db,
    int $userID,
    array $user,
    array $metaCommon,
    array $setParts,
    bool $hasGroup,
    bool $hasFlag,
    bool $hasGroupID,
    bool $hasGroupKod,
    int $oldID,
    int $gid,
    string $oldKod,
    string $gkod,
    int $oldFlag,
    ?int $flag
): ?int {
    if (!function_exists('audit_event')) {
        return null;
    }

    $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
    $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
    $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
    $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label($nama, $nostaf)
        : $nama;
    $message = audit_format_message('User group or access flag updated', $actorLabel);
    $derivedUserId = deriveUserSetGroupAuditUserId($db);

    $eventId = audit_event([
        'event_type' => 'UPDATE',
        'severity' => 'INFO',
        'outcome' => 'SUCCESS',
        'target_type' => 'user',
        'target_id' => (string)$userID,
        'target_label' => 'User: ' . ($user['f_nama'] ?? 'Unknown'),
        'message' => $message,
        'request_id' => $requestId,
        'session_id' => session_id() ?: null,
        'user_id' => $derivedUserId,
        'actor_label' => $actorLabel,
        'meta' => array_merge($metaCommon, [
            'updated_fields' => array_values($setParts),
        ]),
    ]);

    if ($eventId) {
        $changeSetId = audit_begin_change($eventId, 'user', (string)$userID, 'User group/access update');
        if ($changeSetId) {
            if ($hasGroup && $hasGroupID && $oldID !== $gid) {
                audit_change($changeSetId, 'f_groupID', (string)$oldID, (string)$gid, 'integer', false);
            }
            if ($hasGroup && $hasGroupKod && $oldKod !== $gkod) {
                audit_change($changeSetId, 'f_groupKod', $oldKod, $gkod, 'string', false);
            }
            if ($hasFlag && $oldFlag !== $flag) {
                audit_change($changeSetId, 'f_flag', (string)$oldFlag, (string)$flag, 'integer', false);
            }
        }
    }

    return $eventId;
}

try {
    // ===== CSRF =====
    if (!isValidCsrfToken()) {
        json_fail((string)__('userGroup_csrf_invalid'), 400);
    }

    $payload = readUserSetGroupPayload();
    $userID = $payload['userID'];
    $groupID = $payload['groupID'];
    $hasGroup = $payload['hasGroup'];
    $hasFlag = $payload['hasFlag'];
    $flag = $payload['flag'];
    $password = $payload['password'];
    $passwordConfirm = $payload['passwordConfirm'];
    $hasPassword = ($password !== '');

    if ($password !== '' && strlen($password) < 6) {
        json_fail((string)__('userList_ajax_password_min'), 422);
    }
    if (($password !== '' || $passwordConfirm !== '') && $password !== $passwordConfirm) {
        json_fail((string)__('userList_ajax_password_confirm_mismatch'), 422);
    }

    /** @var PDO $db */
    $db = Database::getInstance('mysql')->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $schema = getUserGroupSchemaFlags($db);
    $hasGroupID = $schema['hasGroupID'];
    $hasGroupKod = $schema['hasGroupKod'];

    $userContext = fetchUserGroupContext($db, $userID, $hasGroupID, $hasGroupKod);
    userListEnsureTargetUserEditable($db, $userID);
    if (($userContext['userCategory'] ?? '') === 'PELAJAR' && function_exists('is_student_mode_enabled') && !is_student_mode_enabled()) {
        json_fail((string)__('studentSearch_mode_disabled'), 403);
    }
    $user = $userContext['user'];
    $targetProtectedStaffId = (string)($user['f_stafID'] ?? '');
    if (isProtectedStaffAccount($targetProtectedStaffId) && !canSelfManageProtectedStaffAccount($targetProtectedStaffId)) {
        try {
            audit_event([
                'event_type' => 'UPDATE',
                'severity' => 'WARN',
                'outcome' => 'DENIED',
                'target_type' => 'user',
                'target_id' => (string)$userID,
                'target_label' => (string)($user['f_nama'] ?? $user['f_stafID'] ?? 'Protected User'),
                'message' => 'UPDATE_USER_ACCESS blocked (protected account)',
                'meta' => [
                    'reason' => 'protected_staff_account',
                    'protected_stafID' => (string)($user['f_stafID'] ?? ''),
                    'attempt_groupID' => $groupID,
                    'attempt_flag' => $flag,
                    'performed_by' => (string)($_SESSION['f_stafID'] ?? ''),
                ],
            ]);
        } catch (Throwable $e) {
            error_log('[user-set-group] Protected account audit failed: ' . $e->getMessage());
        }

        json_fail((string)__('userList_protected_self_manage_only'), 403);
    }
    $oldID = $userContext['oldID'];
    $oldKod = $userContext['oldKod'];
    $oldName = $userContext['oldName'];
    $oldFlag = $userContext['oldFlag'];

    $targetGroup = resolveTargetGroup($db, $hasGroup, $groupID, $oldID, $oldKod, $oldName);
    if ($hasGroup) {
        userListEnsureAssignableGroup($db, $groupID);
    }
    $gid = $targetGroup['gid'];
    $gkod = $targetGroup['gkod'];
    $gnam = $targetGroup['gnam'];

    $noopState = evaluateNoopState($hasGroup, $hasFlag, $hasPassword, $hasGroupID, $hasGroupKod, $oldID, $oldKod, $oldFlag, $gid, $gkod, $flag);
    $same = $noopState['same'];

    $metaCommon = buildCommonAuditMeta($user);

    if ($same) {
        $eventId = auditUserSetGroupNoop(
            $user,
            $metaCommon,
            $hasGroup,
            $hasFlag,
            $hasGroupID,
            $hasGroupKod,
            $oldID,
            $gid,
            $oldKod,
            $gkod,
            $oldFlag,
            $flag
        );

        $noopMessage = $hasGroup 
            ? (string)__('userList_ajax_no_change_group')
            : (string)__('userList_ajax_no_change_access');
        
        json_ok([
            'message' => $noopMessage,
            'group'   => $hasGroup ? ['id'=>$gid, 'kod'=>$gkod, 'nama'=>$gnam] : null,
            'groupName' => $hasGroup ? $gnam : null, // ✅ Add groupName at top level for easier access
            'flag'    => $hasFlag ? $flag : null,
            'audit'   => ['event_id'=>$eventId, 'status'=>'noop']
        ]);
    }

    // ===== Transaksi: update + audit =====
    $db->beginTransaction();
    error_log("[user-set-group] Transaction started");

    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';
    $updatePlan = buildUserUpdateStatement($hasGroup, $hasFlag, $hasGroupID, $hasGroupKod, $userID, $gid, $gkod, $flag, $password, $passwordHash);
    $setParts = $updatePlan['setParts'];
    $params = $updatePlan['params'];
    $sql = $updatePlan['sql'];
    executeUserUpdate($db, $sql, $params, $userID);

    // Commit transaction FIRST (before audit to ensure update succeeds)
    $db->commit();
    error_log("[user-set-group] Transaction committed successfully");

    // Audit: Log user group/flag update dengan field changes
    $eventId = null;
    try {
        $eventId = auditUserSetGroupUpdate(
            $db,
            $userID,
            $user,
            $metaCommon,
            $setParts,
            $hasGroup,
            $hasFlag,
            $hasGroupID,
            $hasGroupKod,
            $oldID,
            $gid,
            $oldKod,
            $gkod,
            $oldFlag,
            $flag
        );
    } catch (\Throwable $auditError) {
        error_log("[user-set-group] Audit error (non-fatal): " . $auditError->getMessage());
        // Continue even if audit fails - update already succeeded
    }

    json_ok([
        'message' => (string)__('userList_success_update_group'),
        'group'   => ['id'=>$gid, 'kod'=>$gkod, 'nama'=>$gnam],
        'groupName' => $gnam, // ✅ Add groupName at top level for easier access
        'flag'    => $hasFlag ? $flag : null,
        'audit'   => ['event_id'=>$eventId, 'status'=>'updated']
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        try {
            $db->rollBack();
        } catch (Throwable $rollbackErr) {
            // Ignore rollback errors
        }
    }
    error_log('[user-set-group] Error: '.$e->getMessage().' | File: '.$e->getFile().' | Line: '.$e->getLine().' | Trace: '.$e->getTraceAsString());
    $errorMsg = (string)__('userList_ajax_system_error');
    json_fail($errorMsg, 500);
}

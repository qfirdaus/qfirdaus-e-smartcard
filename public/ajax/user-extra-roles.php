<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-extra-roles.php
// Manage additional roles for a user (tbl_ref_access)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

function group_category_for_scope(string $scope): ?string {
    return match (strtolower(trim($scope))) {
        'staff', 'staf' => 'STAF',
        'student', 'pelajar' => 'PELAJAR',
        'public', 'umum' => 'UMUM',
        default => null,
    };
}

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../includes/functions-db.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/AuditLogger.php';

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => (string)__('userList_ajax_invalid_data')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string)($data['action'] ?? 'get');
    $scope = strtolower(trim((string)($data['scope'] ?? 'staff')));
    $scopeCategory = group_category_for_scope($scope);
    $userID = (int)($data['userID'] ?? 0);
    if ($userID <= 0) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => (string)__('userList_err_param')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);
    if (!isValidCsrfToken()) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($scopeCategory === 'PELAJAR' && function_exists('is_student_mode_enabled') && !is_student_mode_enabled()) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => (string)__('studentSearch_mode_disabled')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Load target user data (userID + legacy stafID + primary role)
    $stmtU = $pdo->prepare("SELECT f_userID, f_loginID, f_stafID, f_groupID, COALESCE(f_categoryUser, '') AS f_categoryUser FROM tbl_m_user WHERE f_userID = :uid LIMIT 1");
    $stmtU->execute([':uid' => $userID]);
    $userRow = $stmtU->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => (string)__('userList_ajax_user_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $targetUserID = (int)($userRow['f_userID'] ?? 0);
    $targetLoginID = trim((string)($userRow['f_loginID'] ?? ''));
    $targetStafID = trim((string)($userRow['f_stafID'] ?? ''));
    $primaryGroupId = (int)($userRow['f_groupID'] ?? 0);
    $targetCategory = strtoupper(trim((string)($userRow['f_categoryUser'] ?? '')));
    if (isProtectedStaffAccount($targetStafID) && !canSelfManageProtectedStaffAccount($targetStafID)) {
        try {
            $logger = new AuditLogger($pdo);
            $logger->logEvent([
                'event_type'  => 'UPDATE',
                'severity'    => 'WARN',
                'outcome'     => 'DENIED',
                'target_type' => 'role_assignment',
                'target_id'   => (string)$userID,
                'target_label'=> 'PROTECTED_ACCOUNT',
                'message'     => 'Blocked attempt to change extra roles for protected account',
                'session_id'  => session_id() ?: null,
                'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
                'actor_label' => ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null),
                'meta'        => [
                    'reason' => 'protected_staff_account',
                    'target_userID' => $targetUserID,
                    'target_loginID' => $targetLoginID !== '' ? $targetLoginID : null,
                    'target_stafID' => $targetStafID,
                    'performed_by' => ($_SESSION['f_stafID'] ?? null),
                ]
            ]);
        } catch (Throwable $e) {
            error_log('[user-extra-roles] Protected account audit failed: ' . $e->getMessage());
        }

        http_response_code(403);
        echo json_encode(['error' => true, 'message' => (string)__('userList_protected_self_manage_only')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($targetCategory === 'PELAJAR' && function_exists('is_student_mode_enabled') && !is_student_mode_enabled()) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => (string)__('studentSearch_mode_disabled')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($targetUserID <= 0) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => (string)__('userList_ajax_invalid_user_param')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get') {
        // All roles
        if ($scopeCategory !== null) {
            $stmtRoles = $pdo->prepare("SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser FROM tbl_m_group WHERE TRIM(COALESCE(f_categoryUser, '')) = :category ORDER BY f_groupName ASC");
            $stmtRoles->execute([':category' => $scopeCategory]);
            $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $roles = $pdo->query("SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser FROM tbl_m_group ORDER BY f_groupName ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        // Existing extra roles
        $stmtE = $pdo->prepare("
            SELECT f_groupID
            FROM tbl_ref_access
            WHERE f_status = 1
              AND (
                f_userID = :uid
                OR (
                  :uid_zero = 0
                  AND TRIM(f_stafID) = :staf
                )
              )
        ");
        $stmtE->execute([':uid' => $targetUserID, ':uid_zero' => $targetUserID, ':staf' => $targetStafID]);
        $existing = array_map('intval', $stmtE->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $existingMap = array_fill_keys($existing, true);

        $out = [];
        foreach ($roles as $r) {
            $rid = (int)($r['f_groupID'] ?? 0);
            $rname = (string)($r['f_groupName'] ?? '');
            if ($rid <= 0 || $rid === $primaryGroupId) continue;
            $out[] = [
                'id' => $rid,
                'name' => $rname,
                'checked' => isset($existingMap[$rid])
            ];
        }

        echo json_encode(['error' => false, 'roles' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'save') {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => (string)__('userList_ajax_invalid_action')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    userListEnsureTargetUserEditable($pdo, $userRow);

    // Save roles (sync)
    $selected = $data['roles'] ?? [];
    if (!is_array($selected)) $selected = [];
    $selectedIdsRaw = array_values(array_unique(array_filter(array_map('intval', $selected), fn($v) => $v > 0)));
    $attemptedPrimary = in_array($primaryGroupId, $selectedIdsRaw, true);
    $selectedIds = $selectedIdsRaw;
    // SECURITY CRITICAL – DO NOT MODIFY: primary role must never be added as extra role
    // Exclude primary role
    $selectedIds = array_values(array_diff($selectedIds, [$primaryGroupId]));

    // Valid roles lookup
    if ($scopeCategory !== null) {
        $stmtValid = $pdo->prepare("SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser FROM tbl_m_group WHERE TRIM(COALESCE(f_categoryUser, '')) = :category");
        $stmtValid->execute([':category' => $scopeCategory]);
        $validRoleRows = $stmtValid->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $validRoleRows = $pdo->query("SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser FROM tbl_m_group")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $allRoleIds = array_map(
        static fn(array $role): int => (int)($role['f_groupID'] ?? 0),
        array_values(array_filter(
            $validRoleRows,
            static fn($role): bool => is_array($role) && userListCanAssignGroup($pdo, $role)
        ))
    );
    $validMap = array_fill_keys($allRoleIds, true);
    $selectedIds = array_values(array_filter($selectedIds, fn($id) => isset($validMap[$id])));

    $stmtE = $pdo->prepare("
        SELECT f_groupID
        FROM tbl_ref_access
        WHERE f_status = 1
          AND (
            f_userID = :uid
            OR (
              :uid_zero = 0
              AND TRIM(f_stafID) = :staf
            )
          )
    ");
    $stmtE->execute([':uid' => $targetUserID, ':uid_zero' => $targetUserID, ':staf' => $targetStafID]);
    $existingIds = array_map('intval', $stmtE->fetchAll(PDO::FETCH_COLUMN) ?: []);
    // SECURITY CRITICAL – DO NOT MODIFY: never remove primary role even if present
    $existingIds = array_values(array_diff($existingIds, [$primaryGroupId]));

    $toAdd = array_values(array_diff($selectedIds, $existingIds));
    $toRemove = array_values(array_diff($existingIds, $selectedIds));

    $pdo->beginTransaction();
    try {
        $logger = new AuditLogger($pdo);

        // Adds
        if (!empty($toAdd)) {
            $ins = $pdo->prepare("
                INSERT INTO tbl_ref_access (f_userID, f_stafID, f_groupID, f_status, f_createdby, f_createddt)
                VALUES (:uid, :staf, :gid, 1, :by, NOW())
            ");
            foreach ($toAdd as $gid) {
                $ins->execute([
                    ':uid' => $targetUserID,
                    ':staf' => $targetStafID,
                    ':gid' => $gid,
                    ':by' => ($_SESSION['f_stafID'] ?? null)
                ]);

                // GOVERNANCE CRITICAL – DO NOT MODIFY: audit logging must remain in-transaction
                $logger->logEvent([
                    'event_type'  => 'UPDATE',
                    'severity'    => 'INFO',
                    'outcome'     => 'SUCCESS',
                    'target_type' => 'role_assignment',
                    'target_id'   => (string)$userID,
                    'target_label'=> 'ADD_ROLE',
                    'message'     => 'Role added',
                    'session_id'  => session_id() ?: null,
                    'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
                    'actor_label' => ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null),
                    'meta'        => [
                        'action' => 'ADD_ROLE',
                        'role_id' => $gid,
                        'performed_by' => ($_SESSION['f_stafID'] ?? null),
                        'target_userID' => $targetUserID,
                        'target_loginID' => $targetLoginID !== '' ? $targetLoginID : null,
                        'target_stafID' => $targetStafID
                    ]
                ]);
            }
        }

        // Removes
        if (!empty($toRemove)) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $params = array_merge([$targetUserID, $targetUserID, $targetStafID], $toRemove);
            $del = $pdo->prepare("
                DELETE FROM tbl_ref_access
                WHERE (
                    f_userID = ?
                    OR (
                        ? = 0
                        AND TRIM(f_stafID) = ?
                    )
                )
                  AND f_groupID IN ($placeholders)
            ");
            $del->execute($params);

            // GOVERNANCE CRITICAL – DO NOT MODIFY: audit logging must remain in-transaction
            foreach ($toRemove as $gid) {
                $logger->logEvent([
                    'event_type'  => 'UPDATE',
                    'severity'    => 'INFO',
                    'outcome'     => 'SUCCESS',
                    'target_type' => 'role_assignment',
                    'target_id'   => (string)$userID,
                    'target_label'=> 'REMOVE_ROLE',
                    'message'     => 'Role removed',
                    'session_id'  => session_id() ?: null,
                    'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
                    'actor_label' => ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null),
                    'meta'        => [
                        'action' => 'REMOVE_ROLE',
                        'role_id' => $gid,
                        'performed_by' => ($_SESSION['f_stafID'] ?? null),
                        'target_userID' => $targetUserID,
                        'target_loginID' => $targetLoginID !== '' ? $targetLoginID : null,
                        'target_stafID' => $targetStafID
                    ]
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // GOVERNANCE CRITICAL – DO NOT MODIFY: blocked attempts must be auditable (non-blocking)
    // Audit blocked attempt (primary role) without affecting valid changes
    if ($attemptedPrimary) {
        try {
            $logger = new AuditLogger($pdo);
            $logger->logEvent([
                'event_type'  => 'UPDATE',
                'severity'    => 'WARN',
                'outcome'     => 'DENIED',
                'target_type' => 'role_assignment',
                'target_id'   => (string)$userID,
                'target_label'=> 'BLOCKED_PRIMARY_ROLE',
                'message'     => 'Blocked attempt to add primary role as additional role',
                'session_id'  => session_id() ?: null,
                'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : null,
                'actor_label' => ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null),
                'meta'        => [
                    'action' => 'BLOCKED_PRIMARY_ROLE',
                    'role_id' => $primaryGroupId,
                    'performed_by' => ($_SESSION['f_stafID'] ?? null),
                    'target_userID' => $targetUserID,
                    'target_loginID' => $targetLoginID !== '' ? $targetLoginID : null,
                    'target_stafID' => $targetStafID
                ]
            ]);
        } catch (Throwable $e) {
            error_log('[user-extra-roles] Blocked primary role audit failed: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'error' => false,
        'message' => (string)__('userList_success_update_roles'),
        'added' => $toAdd,
        'removed' => $toRemove
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[user-extra-roles] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => (string)__('userList_ajax_roles_update_failed')], JSON_UNESCAPED_UNICODE);
}

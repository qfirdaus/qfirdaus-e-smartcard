<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-delete.php
// Delete user from tbl_m_user
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
    logAjaxUnexpectedOutput('user-delete:init.php', $initOutput);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonErrorResponse((string)__('userList_ajax_method_not_allowed'), 405);
    }

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    // Rate limiting: max 20 requests per 60 seconds
    if (!checkRateLimit('user_delete', 20, 60)) {
        jsonErrorResponse((string)__('userList_ajax_rate_limited'), 429);
    }

    // SECURITY CRITICAL – DO NOT MODIFY: backend permission guard
    // Check permission - hanya Super Admin boleh delete
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
    
    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);
    $userModel = new User($pdo);
    
    $currentStafID = $_SESSION['f_stafID'] ?? '';
    $currentProfile = $userModel->getProfile($currentStafID);
    
    $isSuperAdmin = $currentProfile && function_exists('is_user_super_admin') && is_user_super_admin($currentProfile, $pdo);

    $normalizeIdentity = static function (?string $value): string {
        return str_replace('-', '', trim((string)$value));
    };

    $readPayload = static function (): array {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            jsonErrorResponse((string)__('userList_ajax_invalid_data'), 400);
        }

        if (!isValidCsrfToken((string)($data['csrf_token'] ?? ''))) {
            jsonErrorResponse((string)__('userGroup_csrf_invalid'), 400);
        }

        $userID = isset($data['userID']) ? (int)$data['userID'] : 0;
        if ($userID <= 0) {
            jsonErrorResponse((string)__('userList_ajax_invalid_user_id'), 400);
        }

        return ['data' => $data, 'userID' => $userID];
    };

    $fetchTargetUser = static function (PDO $pdo, int $userID): array {
        $checkSql = "SELECT f_userID, f_loginID, f_stafID, f_nopekerja, f_nama, COALESCE(f_categoryUser, '') AS f_categoryUser FROM tbl_m_user WHERE f_userID = :userID LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':userID' => $userID]);
        $userData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            jsonErrorResponse((string)__('userList_ajax_user_not_found_in_system'), 404);
        }

        return $userData;
    };

    $isDeletingOwnUserAccount = static function (array $currentProfile, array $targetUser, int $targetUserID) use ($normalizeIdentity): bool {
        $currentUserId = (int)($currentProfile['f_userID'] ?? ($_SESSION['f_userID'] ?? 0));
        $currentStafIDNormalized = $normalizeIdentity((string)($currentProfile['f_stafID'] ?? ($_SESSION['f_stafID'] ?? '')));
        $currentNoPekerjaNormalized = $normalizeIdentity((string)($currentProfile['f_nopekerja'] ?? ($_SESSION['f_nopekerja'] ?? '')));
        $targetStafIDNormalized = $normalizeIdentity((string)($targetUser['f_stafID'] ?? ''));
        $targetNoPekerjaNormalized = $normalizeIdentity((string)($targetUser['f_nopekerja'] ?? ''));

        return
            ($currentUserId > 0 && $targetUserID === $currentUserId) ||
            ($currentStafIDNormalized !== '' && $targetStafIDNormalized !== '' && $currentStafIDNormalized === $targetStafIDNormalized) ||
            ($currentNoPekerjaNormalized !== '' && $targetNoPekerjaNormalized !== '' && $currentNoPekerjaNormalized === $targetNoPekerjaNormalized);
    };

    $deriveAuditUserId = static function () use ($userModel, $currentProfile): ?int {
        if (!empty($_SESSION['user']['f_userID']) && is_numeric($_SESSION['user']['f_userID'])) {
            return (int)$_SESSION['user']['f_userID'];
        }
        if (!empty($_SESSION['f_userID']) && is_numeric($_SESSION['f_userID'])) {
            return (int)$_SESSION['f_userID'];
        }

        $candidate = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? ($currentProfile['f_nopekerja'] ?? null) ?? $_SESSION['f_stafID'] ?? null;
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
                $profile = $userModel->getProfile($_SESSION['f_stafID']);
                if (!empty($profile['f_nopekerja'])) {
                    $value = $profile['f_nopekerja'];
                    if (is_numeric($value)) {
                        return (int)$value;
                    }
                    if (preg_match('/^(\d+)/', (string)$value, $m2)) {
                        return (int)$m2[1];
                    }
                }
            } catch (Throwable $e) {
                error_log('[user-delete] Derived user_id override failed: ' . $e->getMessage());
            }
        }

        return null;
    };

    $buildDeleteAuditData = static function (int $userID, array $userData, ?string $loggedInStafID) use ($deriveAuditUserId): array {
        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
        $sessionId = session_id() ?: null;
        $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
        $loginID = $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ($_SESSION['f_stafID'] ?? null);
        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label($nama, $loginID)
            : $nama;
        $userId = $deriveAuditUserId();
        $message = audit_format_message('User permanently deleted', $actorLabel);

        $auditData = [
            'event_type' => 'DELETE',
            'severity' => 'WARN',
            'outcome' => 'SUCCESS',
            'target_type' => 'user',
            'target_id' => (string)$userID,
            'target_label' => 'User: ' . ($userData['f_nama'] ?? 'Unknown'),
            'message' => $message,
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'actor_label' => $actorLabel,
            'meta' => [
                'deleted_stafID' => $userData['f_stafID'] ?? null,
                'deleted_loginID' => $userData['f_loginID'] ?? null,
                'deleted_nopekerja' => $userData['f_nopekerja'] ?? null,
                'reason' => 'Deleted via user management',
                'deleted_by' => $loggedInStafID,
            ],
        ];

        error_log("[user-delete] Audit prep: request_id={$requestId}, session_id={$sessionId}, user_id=" . ($userId ?? 'null') . ", actor={$actorLabel}, target_userID={$userID}");

        return $auditData;
    };

    $logDeleteAudit = static function (int $userID, array $userData, ?string $loggedInStafID) use ($buildDeleteAuditData, $pdo): ?int {
        error_log('[user-delete] Starting audit logging...');

        if (!function_exists('audit_event')) {
            error_log('[user-delete] ERROR: audit_event() function not found - check if audit_helper.php is loaded');
            $auditHelperPath = __DIR__ . '/../setting/helper/audit_helper.php';
            if (file_exists($auditHelperPath)) {
                require_once $auditHelperPath;
                error_log('[user-delete] Manually loaded audit_helper.php');
            }
        }

        if (!function_exists('audit_event')) {
            error_log('[user-delete] ERROR: audit_event() function still not available after manual load attempt');
            return null;
        }

        $auditData = $buildDeleteAuditData($userID, $userData, $loggedInStafID);
        error_log("[user-delete] Calling audit_event with data: " . json_encode($auditData));

        $eventId = audit_event($auditData);
        error_log("[user-delete] Audit event result: event_id=" . ($eventId ?? 'null') . ", request_id=" . ($auditData['request_id'] ?? 'null') . ", session_id=" . ($auditData['session_id'] ?? 'null') . ", user_id=" . ($auditData['user_id'] ?? 'null'));

        if ($eventId) {
            error_log("[user-delete] SUCCESS: Audit event logged with event_id={$eventId}");
            return $eventId;
        }

        error_log('[user-delete] WARNING: audit_event() returned null/0 - trying direct AuditLogger fallback');
        try {
            require_once __DIR__ . '/../classes/AuditLogger.php';
            $logger = new AuditLogger($pdo);
            $eventId = $logger->logEvent($auditData);
            error_log("[user-delete] Direct AuditLogger call result: event_id={$eventId}");
            return $eventId ?: null;
        } catch (Throwable $fallbackError) {
            error_log('[user-delete] Direct AuditLogger fallback also failed: ' . $fallbackError->getMessage() . ' | Trace: ' . $fallbackError->getTraceAsString());
            return null;
        }
    };

    $clearDeleteCaches = static function (): void {
        if (isset($_SESSION['userlist_cache']['staf_options_list'])) {
            unset($_SESSION['userlist_cache']['staf_options_list']);
        }
    };

    $payload = $readPayload();
    $userID = $payload['userID'];
    $userData = $fetchTargetUser($pdo, $userID);
    userListEnsureTargetUserDeletable($pdo, $userData);
    if (strtoupper(trim((string)($userData['f_categoryUser'] ?? ''))) === 'PELAJAR' && function_exists('is_student_mode_enabled') && !is_student_mode_enabled()) {
        jsonErrorResponse((string)__('studentSearch_mode_disabled'), 403);
    }

    if (isProtectedStaffAccount((string)($userData['f_stafID'] ?? ''))) {
        try {
            audit_event([
                'event_type' => 'DELETE',
                'severity' => 'WARN',
                'outcome' => 'DENIED',
                'target_type' => 'user',
                'target_id' => (string)$userID,
                'target_label' => (string)($userData['f_nama'] ?? $userData['f_stafID'] ?? 'Protected User'),
                'message' => 'DELETE_USER blocked (protected account)',
                'meta' => [
                    'reason' => 'protected_staff_account',
                    'protected_stafID' => (string)($userData['f_stafID'] ?? ''),
                    'deleted_by' => (string)($_SESSION['f_stafID'] ?? ''),
                ],
            ]);
        } catch (Throwable $e) {
            error_log('[user-delete] Protected account audit failed: ' . $e->getMessage());
        }

        jsonErrorResponse((string)__('userList_protected_delete_denied'), 403);
    }

    $isDeletingOwnAccount = $isDeletingOwnUserAccount($currentProfile ?: [], $userData, $userID);

    if ($isDeletingOwnAccount) {
        jsonErrorResponse((string)__('userList_ajax_delete_self_denied'), 403);
    }

    $loggedInStafID = $_SESSION['f_stafID'] ?? null;

    // Hard delete: DELETE FROM tbl_m_user
    $deleteSql = "DELETE FROM tbl_m_user WHERE f_userID = :userID";

    $deleteStmt = $pdo->prepare($deleteSql);
    $result = $deleteStmt->execute([
        ':userID' => $userID
    ]);

    if (!$result) {
        throw new Exception((string)__('userList_ajax_delete_failed_backend'));
    }

    try {
        $logDeleteAudit($userID, $userData, $loggedInStafID);
    } catch (\Throwable $e) {
        error_log('[user-delete] Audit logging EXCEPTION: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
    }

    $clearDeleteCaches();
    
    jsonSuccessResponse([
        'message' => (string)__('userList_success_delete'),
        'userID' => $userID
    ]);

} catch (PDOException $e) {
    error_log('[user-delete] PDO Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
} catch (Throwable $e) {
    error_log('[user-delete] Error: ' . $e->getMessage());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
}

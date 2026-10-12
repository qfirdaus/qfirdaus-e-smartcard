<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../controllers/AuditCenterController.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json; charset=utf-8');

function ac(string $key, string $fallback): string
{
    $value = __('audit_center_' . $key);
    return ($value === 'audit_center_' . $key || $value === null || $value === '') ? $fallback : (string)$value;
}

function ac_normalize_login_id(?string $loginId): string
{
    return function_exists('auth_normalize_login_id')
        ? auth_normalize_login_id($loginId)
        : trim((string)$loginId);
}

function ac_normalize_scope_key(string $scopeType, ?string $scopeKey): string
{
    $scopeType = strtoupper(trim($scopeType));
    $scopeKey = trim((string)$scopeKey);
    if ($scopeType !== 'LOGIN_IP' || $scopeKey === '') {
        return $scopeKey;
    }

    $parts = explode('|', $scopeKey, 2);
    $normalizedLoginId = ac_normalize_login_id($parts[0] ?? '');
    $normalizedIp = trim((string)($parts[1] ?? ''));
    if ($normalizedLoginId === '' || $normalizedIp === '') {
        return $scopeKey;
    }

    return $normalizedLoginId . '|' . $normalizedIp;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse(ac('action_method_not_allowed', 'Method not allowed.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    if (!checkRateLimit('audit_center_action', 20, 60)) {
        jsonErrorResponse(ac('action_rate_limited', 'Terlalu banyak tindakan. Sila tunggu sebentar.'), 429);
    }

    $controller = new AuditCenterController();
    if (!$controller->isSuperAdmin()) {
        jsonErrorResponse(ac('access_denied_text', 'Halaman Audit Center hanya boleh dicapai oleh super admin.'), 403);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonErrorResponse(ac('action_invalid_payload', 'Data tindakan tidak sah.'), 400);
    }

    $action = strtolower(trim((string)($input['action'] ?? '')));
    if (!in_array($action, ['clear_lockout', 'clear_throttle', 'terminate_session'], true)) {
        jsonErrorResponse(ac('action_invalid_type', 'Jenis tindakan tidak sah.'), 400);
    }

    $pdo = Database::pdoMysql();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $userModel = new User($pdo);
    $actorLoginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
    $actorName = trim((string)($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? ''));
    $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($action === 'clear_lockout') {
        $loginId = ac_normalize_login_id((string)($input['login_id'] ?? ''));
        if ($loginId === '') {
            jsonErrorResponse(ac('action_lockout_missing', 'Login ID untuk lockout tidak diberikan.'), 422);
        }

        $updated = $userModel->clearLoginLockout($loginId, $clientIp, $userAgent);
        if (!$updated) {
            jsonErrorResponse(ac('action_lockout_failed', 'Gagal membersihkan lockout tersebut.'), 500);
        }

        if (function_exists('audit_event')) {
            audit_event([
                'event_type' => 'ADMIN_ACTION',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'login_lockout',
                'target_id' => $loginId,
                'target_label' => $loginId,
                'message' => ac('action_lockout_cleared', 'Lockout berjaya dibersihkan.'),
                'login_id' => $actorLoginId !== '' ? $actorLoginId : null,
                'actor_label' => $actorName !== '' ? $actorName : null,
                'meta' => [
                    'action' => 'clear_lockout',
                    'target_login_id' => $loginId,
                ],
            ]);
        }

        jsonSuccessResponse(['message' => ac('action_lockout_cleared', 'Lockout berjaya dibersihkan.')]);
    }

    if ($action === 'clear_throttle') {
        $scopeType = strtoupper(trim((string)($input['scope_type'] ?? '')));
        $scopeKey = ac_normalize_scope_key($scopeType, (string)($input['scope_key'] ?? ''));
        if ($scopeType === '' || $scopeKey === '') {
            jsonErrorResponse(ac('action_throttle_missing', 'Maklumat throttle tidak lengkap.'), 422);
        }

        $updated = $userModel->clearLoginThrottle($scopeType, $scopeKey, $clientIp, $userAgent);
        if (!$updated) {
            jsonErrorResponse(ac('action_throttle_failed', 'Gagal membersihkan throttle tersebut.'), 500);
        }

        if (function_exists('audit_event')) {
            audit_event([
                'event_type' => 'ADMIN_ACTION',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'login_throttle',
                'target_id' => $scopeKey,
                'target_label' => $scopeType . ' : ' . $scopeKey,
                'message' => ac('action_throttle_cleared', 'Throttle berjaya dibersihkan.'),
                'login_id' => $actorLoginId !== '' ? $actorLoginId : null,
                'actor_label' => $actorName !== '' ? $actorName : null,
                'meta' => [
                    'action' => 'clear_throttle',
                    'scope_type' => $scopeType,
                    'scope_key' => $scopeKey,
                ],
            ]);
        }

        jsonSuccessResponse(['message' => ac('action_throttle_cleared', 'Throttle berjaya dibersihkan.')]);
    }

    $sessionId = trim((string)($input['session_id'] ?? ''));
    if ($sessionId === '') {
        jsonErrorResponse(ac('action_session_missing', 'ID sesi tidak diberikan.'), 422);
    }
    if ($sessionId === session_id()) {
        jsonErrorResponse(ac('action_session_current_forbidden', 'Sesi semasa tidak boleh ditamatkan dari Audit Center.'), 400);
    }

    $stmt = $pdo->prepare("
        UPDATE audit_session
        SET ended_at = NOW(6)
        WHERE session_id = :session_id
          AND ended_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':session_id' => $sessionId]);

    if ((int)$stmt->rowCount() < 1) {
        jsonErrorResponse(ac('action_session_failed', 'Gagal menamatkan sesi tersebut atau sesi sudah tamat.'), 500);
    }

    if (function_exists('audit_event')) {
        audit_event([
            'event_type' => 'ADMIN_ACTION',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'session',
            'target_id' => $sessionId,
            'target_label' => $sessionId,
            'message' => ac('action_session_terminated', 'Sesi berjaya ditamatkan.'),
            'login_id' => $actorLoginId !== '' ? $actorLoginId : null,
            'actor_label' => $actorName !== '' ? $actorName : null,
                'meta' => [
                    'action' => 'terminate_session',
                    'target_session_id' => $sessionId,
                    'hard_kill_mode' => 'bootstrap_enforced',
                ],
            ]);
        }

    jsonSuccessResponse(['message' => ac('action_session_terminated', 'Sesi berjaya ditamatkan.')]);
} catch (Throwable $e) {
    error_log('[audit-center-action] ' . $e->getMessage());
    if (function_exists('audit_event')) {
        try {
            audit_event([
                'event_type' => 'ADMIN_ACTION', 'severity' => 'WARNING', 'outcome' => 'FAILURE',
                'target_type' => 'audit_center_action', 'target_id' => 'system-error',
                'target_label' => 'Audit Center action', 'message' => 'Audit Center action failed',
                'meta' => ['reason_type' => get_class($e)],
            ]);
        } catch (Throwable $auditError) {
            error_log('[audit-center-action] Failure audit error: ' . $auditError->getMessage());
        }
    }
    jsonErrorResponse(ac('action_server_error', 'Ralat sistem semasa memproses tindakan Audit Center.'), 500);
}
